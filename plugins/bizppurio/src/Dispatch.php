<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Support\Clock;
use Throwable;

final class Dispatch
{
    public function __construct(private Store $store, private Settings $settings, private Templates $templates,
        private Api $api, private SecretCipher $cipher, private string $secret, private string $storageDir)
    {
    }

    public function send(array $input): array
    {
        $environment = Input::environment($input['environment'] ?? null);
        $idempotency = Input::text($input['idempotency_key'] ?? null, '요청 식별자', 128);
        $phone = Input::phone($input['phone'] ?? null);
        $templateId = Input::id($input['template_id'] ?? null);
        $revision = Input::id($input['revision'] ?? null);
        $variables = $input['variables'] ?? [];
        if (!is_array($variables)) throw DomainError::validation(['variables' => '변수 입력을 확인해 주세요.']);
        ksort($variables);
        $reference = Input::text($input['reference'] ?? '', '업무 참조', 100, true);
        $key = hash('sha256', $environment . "\0" . $idempotency);
        $requestHash = $this->digest(json_encode([$environment, $templateId, $revision, $phone, $variables, $reference], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = $this->byKey($key);
        if ($existing !== null) return $this->sameRequest($existing, $requestHash);
        if ($this->store->db->pdo()->inTransaction()) throw DomainError::internal('업무 트랜잭션을 완료한 뒤 발송해 주세요.');
        $configRevision = $input['config_revision'] ?? null;
        return Locks::run($this->storageDir . '/upgrade.lock', function () use ($environment, $templateId, $revision, $phone, $variables, $reference, $key, $requestHash, $configRevision): array {
            return Locks::run($this->settings->lockPath(), function () use ($environment, $templateId, $revision, $phone, $variables, $reference, $key, $requestHash, $configRevision): array {
                $existing = $this->byKey($key);
                if ($existing !== null) return $this->sameRequest($existing, $requestHash);
                $settings = $this->requireEnabled($environment, $phone);
                if ($configRevision !== null && $configRevision !== $settings['revision']) throw DomainError::validation(['settings' => '연결 설정이 변경되었습니다. 미리보기를 다시 확인해 주세요.']);
                $preview = $this->templates->preview($templateId, $variables, $revision);
                if ($preview['environment'] !== $environment) throw DomainError::validation(['environment' => '템플릿의 환경과 발송 환경이 다릅니다.']);
                $id = bin2hex(random_bytes(16));
                $now = Clock::timestamp();
                $payload = ['phone' => $phone, 'content' => $preview['content'], 'reference' => $reference,
                    'template_revision' => $revision, 'from' => $settings['from'], 'account_id' => hash('sha256', $settings['account'])];
                try {
                    $this->store->insert('bp_dispatches', ['id' => $id, 'environment' => $environment, 'config_revision' => $settings['revision'],
                        'idempotency_key' => $key, 'request_hash' => $requestHash, 'template_id' => $templateId, 'template_name' => $preview['name'],
                        'payload' => $this->cipher->encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                        'phone_mask' => substr($phone, 0, 3) . '-****-' . substr($phone, -4), 'phone_hash' => $this->phoneHash($phone),
                        'submission' => 'prepared', 'delivery' => 'pending', 'created_at' => $now, 'updated_at' => $now, 'retry_at' => 0, 'attempts' => 0]);
                } catch (DomainError $e) {
                    $existing = $this->byKey($key);
                    if ($existing !== null) return $this->sameRequest($existing, $requestHash);
                    throw DomainError::serviceUnavailable('발송 요청을 저장하지 못했습니다.');
                }
                return $this->transmit($this->row($id), $settings, $payload);
            });
        }, LOCK_SH);
    }

    public function retry(string $id): array
    {
        return Locks::run($this->storageDir . '/upgrade.lock', function () use ($id): array {
            return Locks::run($this->settings->lockPath(), function () use ($id): array {
                $row = $this->row($id);
                $last = $this->attempts($id);
                $last = $last === [] ? null : $last[count($last) - 1];
                if ($row['submission'] !== 'rejected' || !in_array($last['result_code'] ?? '', ['5002', 'AUTH'], true)
                    || $row['payload'] === '' || (int) $row['attempts'] >= 3 || (int) $row['retry_at'] > Clock::timestamp()) {
                    throw DomainError::validation(['retry' => '재시도할 수 없습니다. 불명확한 발송은 업체 이력에서 먼저 확인해 주세요.']);
                }
                $payload = $this->payload($row);
                $settings = $this->requireEnabled($row['environment'], $payload['phone']);
                if ($row['config_revision'] !== $settings['revision']) throw DomainError::validation(['settings' => '설정이 변경된 발송은 새 미리보기에서 확인해 주세요.']);
                $template = $this->templates->get($row['template_id']);
                if (!$template['enabled'] || $template['revision'] !== $payload['template_revision']) throw DomainError::validation(['template' => '템플릿이 변경되어 재시도할 수 없습니다.']);
                return $this->transmit($row, $settings, $payload);
            });
        }, LOCK_SH);
    }

    private function transmit(array $row, array $settings, array $payload, bool $refreshToken = false): array
    {
        $id = $row['id'];
        $attemptId = bin2hex(random_bytes(16));
        $refkey = bin2hex(random_bytes(16));
        $now = Clock::timestamp();
        $this->store->db->transaction(function () use ($row, $id, $attemptId, $refkey, $now): void {
            $changed = $this->store->db->update('bp_dispatches', ['submission' => 'sending', 'updated_at' => $now,
                'attempts' => (int) $row['attempts'] + 1], 'id = :id AND submission IN (:prepared, :rejected)',
                ['id' => $id, 'prepared' => 'prepared', 'rejected' => 'rejected']);
            if ($changed !== 1) throw DomainError::validation(['dispatch' => '이미 처리 중인 발송입니다.']);
            $this->store->insert('bp_attempts', ['id' => $attemptId, 'dispatch_id' => $id, 'sequence_no' => (int) $row['attempts'] + 1, 'refkey' => $refkey, 'messagekey' => '',
                'submission' => 'prepared', 'result_code' => '', 'http_status' => 0, 'created_at' => $now]);
        });
        try {
            $token = $this->api->token($settings, $refreshToken);
        } catch (Throwable $e) {
            $this->record($id, $attemptId, 'rejected', 'AUTH', 0, '');
            return $this->detail($id);
        }
        $this->store->update('bp_attempts', $attemptId, ['submission' => 'sending']);
        try {
            $response = $this->api->post($settings, '/v3/message', ['refkey' => $refkey, 'type' => 'at',
                'from' => $payload['from'], 'to' => $payload['phone'], 'content' => $payload['content']], $token);
            $status = (int) $response['status'];
            $body = $response['body'];
            $code = is_scalar($body['code'] ?? null) ? (string) $body['code'] : '';
            $messagekey = is_string($body['messagekey'] ?? null) && preg_match('/^[A-Za-z0-9_#.-]{1,128}$/D', $body['messagekey']) ? $body['messagekey'] : '';
            $echoValid = !isset($body['refkey']) || $body['refkey'] === $refkey;
            if ($status === 200 && $code === '1000' && $messagekey !== '' && $echoValid) {
                $this->record($id, $attemptId, 'accepted', $code, $status, $messagekey);
            } elseif (in_array($status, [200, 400, 401, 403, 429], true) && $echoValid
                && in_array($code, ['2000','3000','3001','3002','3003','3004','3005','3006','3007','3008','3009','3010','3014','5002'], true)) {
                $this->record($id, $attemptId, 'rejected', $code, $status, '');
                if (!$refreshToken && in_array($code, ['3002', '3005'], true) && (int) $row['attempts'] < 2) {
                    return $this->transmit($this->row($id), $settings, $payload, true);
                }
            } else {
                $this->record($id, $attemptId, 'unknown', ctype_digit($code) && strlen($code) <= 16 ? $code : 'RESPONSE', $status, '');
            }
        } catch (Throwable $e) {
            // 접수 후 로컬 저장 실패도 재발송하지 않는다. 저장이 계속 실패하면 sending을 남긴다.
            try { $this->record($id, $attemptId, 'unknown', 'TRANSPORT', 0, ''); } catch (Throwable $ignored) {}
        }
        return $this->detail($id);
    }

    private function record(string $id, string $attemptId, string $state, string $code, int $http, string $messagekey): void
    {
        $this->store->db->transaction(function () use ($id, $attemptId, $state, $code, $http, $messagekey): void {
            $this->store->db->execute('UPDATE ' . $this->store->db->table('bp_dispatches') . ' SET updated_at = updated_at WHERE id = ?', [$id]);
            // 먼저 도착한 웹훅이 채운 키·성공 상태는 늦은 HTTP 응답으로 되돌리지 않는다.
            $attempt = $this->store->find('bp_attempts', $attemptId);
            if ($attempt['messagekey'] !== '') {
                if ($messagekey !== '' && $messagekey !== $attempt['messagekey']) $state = 'unknown';
                else $state = 'accepted';
                $messagekey = $attempt['messagekey'];
            }
            $this->store->update('bp_attempts', $attemptId, ['submission' => $state, 'result_code' => $code, 'http_status' => $http, 'messagekey' => $messagekey]);
            $this->store->update('bp_dispatches', $id, ['submission' => $state, 'updated_at' => Clock::timestamp(),
                'retry_at' => in_array($code, ['AUTH', '5002'], true) ? Clock::timestamp() + 30 : 0]);
        });
    }

    public function refreshResult(string $id): void
    {
        Locks::run($this->storageDir . '/upgrade.lock', function () use ($id): void {
            $row = $this->row($id);
            if ((int) $row['created_at'] < Clock::timestamp() - 35 * 86400) throw DomainError::validation(['report' => '결과 재요청 보관 기간이 지났습니다.']);
            $attempts = array_values(array_filter($this->attempts($id), static fn (array $a): bool => $a['messagekey'] !== ''));
            if ($attempts === []) throw DomainError::validation(['report' => '메시지 키가 없습니다. 업체 발송 이력에서 확인해 주세요.']);
            $settings = $this->settings->read($row['environment']);
            if ($settings === null || hash('sha256', $settings['account']) !== ($this->payload($row)['account_id'] ?? '')) throw DomainError::validation(['report' => '계정 변경 전 발송입니다. 업체 이력에서 확인해 주세요.']);
            $response = $this->api->post($settings, '/v2/report', ['messagekey' => $attempts[count($attempts) - 1]['messagekey']], $this->api->token($settings));
            if ($response['status'] !== 200 || (string) ($response['body']['code'] ?? '') !== '1000') throw DomainError::serviceUnavailable('결과 재요청에 실패했습니다. 잠시 후 확인해 주세요.');
        }, LOCK_SH);
    }

    public function history(array $filter): array
    {
        $environment = Input::environment($filter['environment'] ?? 'test');
        $page = filter_var($filter['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
        $params = [$environment];
        $where = 'environment = ?';
        $status = $filter['status'] ?? '';
        if ($status !== '') {
            if (!in_array($status, ['prepared','sending','accepted','rejected','unknown'], true)) throw DomainError::validation(['status' => '조회 상태를 확인해 주세요.']);
            $where .= ' AND submission = ?'; $params[] = $status;
        }
        if (($filter['template_id'] ?? '') !== '') { $where .= ' AND template_id = ?'; $params[] = Input::id($filter['template_id']); }
        foreach (['from' => '>=', 'until' => '<'] as $field => $operator) {
            if (($filter[$field] ?? '') === '') continue;
            $date = Input::text($filter[$field], '조회 날짜', 10);
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Seoul'));
            if ($parsed === false || $parsed->format('Y-m-d') !== $date) throw DomainError::validation(['date' => '조회 날짜를 확인해 주세요.']);
            $where .= ' AND created_at ' . $operator . ' ?';
            $params[] = ($field === 'until' ? $parsed->modify('+1 day') : $parsed)->getTimestamp();
        }
        $table = $this->store->db->table('bp_dispatches');
        $count = (int) $this->store->db->selectOne('SELECT COUNT(*) AS n FROM ' . $table . ' WHERE ' . $where, $params)['n'];
        $rows = $this->store->db->select('SELECT * FROM ' . $table . ' WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20), $params);
        return ['items' => array_map($this->safeRow(...), $rows), 'page' => $page, 'total' => $count];
    }

    public function detail(string $id): array
    {
        $row = $this->row($id);
        $result = $this->safeRow($row);
        $result['snapshot'] = $row['payload'] === '' ? null : $this->payload($row);
        if ($result['snapshot'] !== null) unset($result['snapshot']['phone']);
        $result['attempts_detail'] = $this->attempts($id);
        $result['receipts'] = $this->store->db->select('SELECT * FROM ' . $this->store->db->table('bp_receipts') . ' WHERE dispatch_id = ? ORDER BY received_at, id', [$id]);
        foreach (['attempts_detail', 'receipts'] as $field) {
            foreach ($result[$field] as &$item) $item['result_description'] = ResultCodes::describe($item['result_code']);
            unset($item);
        }
        return $result;
    }

    public function purge(): int
    {
        $rows = $this->store->db->select('SELECT id FROM ' . $this->store->db->table('bp_dispatches') . " WHERE created_at < ? AND payload <> '' LIMIT 100", [Clock::timestamp() - 90 * 86400]);
        foreach ($rows as $row) $this->store->update('bp_dispatches', $row['id'], ['payload' => '', 'phone_mask' => '', 'phone_hash' => '']);
        return count($rows);
    }

    public function phoneHash(string $phone): string { return $this->digest('phone:' . $phone); }
    private function digest(string $value): string { return hash_hmac('sha256', $value, hash_hmac('sha256', 'bizppurio:data:v1', $this->secret, true)); }
    private function byKey(string $key): ?array { return $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('bp_dispatches') . ' WHERE idempotency_key = ?', [$key]); }
    private function row(string $id): array { return $this->store->find('bp_dispatches', Input::id($id)) ?? throw DomainError::notFound('발송 이력을 찾을 수 없습니다.'); }
    private function payload(array $row): array { return json_decode($this->cipher->decrypt($row['payload']), true, 32, JSON_THROW_ON_ERROR); }
    private function attempts(string $id): array { return $this->store->db->select('SELECT * FROM ' . $this->store->db->table('bp_attempts') . ' WHERE dispatch_id = ? ORDER BY sequence_no', [$id]); }

    private function safeRow(array $row): array
    {
        unset($row['payload'], $row['phone_hash'], $row['request_hash'], $row['idempotency_key']);
        if (in_array($row['submission'], ['sending', 'prepared'], true) && (int) $row['updated_at'] < Clock::timestamp() - 120) $row['submission'] = 'unknown';
        return $row;
    }

    private function sameRequest(array $row, string $hash): array
    {
        if (!hash_equals($row['request_hash'], $hash)) throw DomainError::validation(['idempotency_key' => '같은 요청 식별자로 다른 내용을 보낼 수 없습니다.']);
        return $this->detail($row['id']);
    }

    private function requireEnabled(string $environment, string $phone): array
    {
        $settings = $this->settings->read($environment);
        if ($settings === null || !$this->settings->enabled($settings)) throw DomainError::validation(['enabled' => '발송이 정지되어 있습니다. 플러그인 설정을 확인해 주세요.']);
        if ($settings['test_only'] && $phone !== $settings['test_phone']) throw DomainError::validation(['phone' => '현재 지정된 테스트 번호로만 발송할 수 있습니다.']);
        return $settings;
    }
}
