<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use Psr\Http\Message\ServerRequestInterface;

final class Results
{
    public function __construct(private Store $store, private Settings $settings, private Dispatch $dispatch, private string $storageDir)
    {
    }

    public function authenticate(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        if (!in_array($query['environment'] ?? null, ['test', 'live'], true) || !is_string($query['token'] ?? null)) return false;
        $settings = $this->settings->read($query['environment']);
        if ($settings === null || !hash_equals($settings['webhook_token'], $query['token'])) return false;
        if ($settings['webhook_ips'] !== []) {
            // REMOTE_ADDR만 사용한다. 임의의 X-Forwarded-For를 신뢰하지 않는다.
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
            if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
            $allowed = false;
            foreach ($settings['webhook_ips'] as $candidate) if (inet_pton($ip) === inet_pton($candidate)) $allowed = true;
            if (!$allowed) return false;
        }
        return true;
    }

    public function receive(string $environment, array $input): void
    {
        Input::environment($environment);
        $fields = [];
        foreach (['MSGID' => 128, 'CMSGID' => 128, 'MEDIA' => 16, 'RESULT' => 16, 'UNIXTIME' => 12] as $field => $max) {
            $fields[$field] = Input::text($input[$field] ?? null, $field, $max);
            if (!preg_match('/^[A-Za-z0-9_#.-]+$/D', $fields[$field])) throw DomainError::validation(['result' => '결과 형식을 확인해 주세요.']);
        }
        if (!ctype_digit($fields['RESULT']) || !ctype_digit($fields['UNIXTIME'])
            || (int) $fields['UNIXTIME'] > Clock::timestamp() + 86400 || (int) $fields['UNIXTIME'] < 1) {
            throw DomainError::validation(['result' => '결과 코드·시각을 확인해 주세요.']);
        }
        $phone = Input::phone($input['PHONE'] ?? null);
        $refkey = Input::text($input['REFKEY'] ?? '', 'REFKEY', 32, true);
        $hash = hash('sha256', json_encode([$environment, $fields, $this->dispatch->phoneHash($phone), $refkey], JSON_THROW_ON_ERROR));
        if ($this->store->find('bp_receipts', $hash) !== null) return;
        Locks::run($this->storageDir . '/upgrade.lock', function () use ($environment, $fields, $phone, $refkey, $hash): void {
            try {
                $this->store->db->transaction(function () use ($environment, $fields, $phone, $refkey, $hash): void {
                    $attempt = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('bp_attempts')
                        . ($refkey === '' ? ' WHERE messagekey = ?' : ' WHERE refkey = ?'), [$refkey === '' ? $fields['CMSGID'] : $refkey]);
                    $row = $attempt === null ? null : $this->store->find('bp_dispatches', $attempt['dispatch_id']);
                    $matches = $row !== null && $row['environment'] === $environment && strtoupper($fields['MEDIA']) === 'AT'
                        && hash_equals($row['phone_hash'], $this->dispatch->phoneHash($phone))
                        && ($attempt['messagekey'] === '' || $attempt['messagekey'] === $fields['CMSGID']);
                    if ($matches) {
                        // 같은 발송의 결과를 직렬화한다. 원격 호출은 이 트랜잭션에 없다.
                        $this->store->db->execute('UPDATE ' . $this->store->db->table('bp_dispatches') . ' SET updated_at = updated_at WHERE id = ?', [$row['id']]);
                        $attempt = $this->store->find('bp_attempts', $attempt['id']);
                        if ($attempt['messagekey'] !== '' && $attempt['messagekey'] !== $fields['CMSGID']) $matches = false;
                    } else {
                        // 미매칭 결과는 개인정보 없이 제한된 개수만 보관한다.
                        $count = (int) $this->store->db->selectOne('SELECT COUNT(*) AS n FROM ' . $this->store->db->table('bp_receipts') . ' WHERE matched = 0')['n'];
                        if ($count >= 1000) throw DomainError::serviceUnavailable('미매칭 결과 확인이 필요합니다.');
                    }
                    $this->store->insert('bp_receipts', ['id' => $hash, 'dispatch_id' => $matches ? $row['id'] : '',
                        'attempt_id' => $matches ? $attempt['id'] : '', 'message_id' => $fields['MSGID'], 'message_key' => $fields['CMSGID'],
                        'media' => strtoupper($fields['MEDIA']), 'result_code' => $fields['RESULT'], 'event_at' => (int) $fields['UNIXTIME'],
                        'received_at' => Clock::timestamp(), 'matched' => $matches ? 1 : 0]);
                    if (!$matches) return;
                    $events = $this->store->db->select('SELECT result_code FROM ' . $this->store->db->table('bp_receipts')
                        . ' WHERE dispatch_id = ? AND matched = 1 ORDER BY event_at, received_at, id', [$row['id']]);
                    $delivery = 'pending';
                    foreach ($events as $event) {
                        $code = $event['result_code'];
                        if ($code === '7000') { $delivery = 'delivered'; break; }
                        $delivery = match (true) {
                            $code === '7307' => 'pending',
                            $code === '7305' => 'uncertain',
                            preg_match('/^7[123]\d{2}$/D', $code) === 1 => 'failed',
                            default => 'uncertain',
                        };
                    }
                    $this->store->update('bp_attempts', $attempt['id'], ['messagekey' => $fields['CMSGID'], 'submission' => 'accepted']);
                    $this->store->update('bp_dispatches', $row['id'], ['submission' => 'accepted', 'delivery' => $delivery, 'updated_at' => Clock::timestamp()]);
                });
            } catch (DomainError $e) {
                if ($this->store->find('bp_receipts', $hash) === null) throw $e;
            }
        }, LOCK_SH);
    }
}
