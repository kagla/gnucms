<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Support\Base64Url;
use GnuCms\Support\Clock;

/** 결제 요청의 생성·조회·검색·상태 전이. 상태 전이는 모두 조건부 UPDATE다. */
final class Requests
{
    public const PER_PAGE = 20;
    public const CHECKOUT_GRACE = 1800;
    public const RETENTION = 90 * 86400;
    private const EXTRA_COLUMNS = ['paid_at', 'transaction_id', 'refunded_amount', 'expires_at', 'dispatch_count', 'last_dispatch_id', 'last_dispatched_at'];
    private SecretCipher $cipher;
    private string $secret;

    public function __construct(private App $app, private Events $events)
    {
        $this->secret = (string) $app->config('auth.secret', '');
        $this->cipher = new SecretCipher($this->secret);
    }

    private function db(): Connection { return $this->app->db(); }

    /** 단건 생성과 CSV 행이 같은 규칙을 쓴다. */
    public static function normalize(array $input, int $defaultHours): array
    {
        $data = [
            'product_name' => self::text($input['product_name'] ?? '', 'product_name', '상품명', 1, 30),
            'product_detail' => self::text($input['product_detail'] ?? '', 'product_detail', '상품 상세', 0, 150),
            'buyer_name' => self::text($input['buyer_name'] ?? '', 'buyer_name', '구매자명', 1, 30),
            'phone' => Phone::normalize($input['phone'] ?? null),
        ];
        $amount = str_replace([',', ' ', '원'], '', is_scalar($input['amount'] ?? null) ? (string) $input['amount'] : '');
        if (!preg_match('/^\d{1,8}$/D', $amount) || (int) $amount < 100) throw DomainError::validation(['amount' => '금액은 100원 이상 99,999,999원 이하의 숫자로 입력해 주세요.']);
        $data['amount'] = (int) $amount;
        $hours = is_scalar($input['expiry_hours'] ?? null) ? trim((string) $input['expiry_hours']) : '';
        if ($hours === '') $hours = (string) $defaultHours;
        if (!preg_match('/^\d{1,3}$/D', $hours) || (int) $hours < 1 || (int) $hours > 720) throw DomainError::validation(['expiry_hours' => '결제기한은 1~720시간입니다.']);
        $data['expiry_hours'] = (int) $hours;
        return $data;
    }

    private static function text(mixed $value, string $field, string $label, int $min, int $max): string
    {
        $value = is_scalar($value) ? trim((string) $value) : null;
        if ($value === null || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) throw DomainError::validation([$field => $label . ' 입력값을 확인해 주세요.']);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) throw DomainError::validation([$field => $label . '은(는) ' . ($min === 0 ? '최대 ' : $min . '~') . $max . '자까지 입력할 수 있습니다.']);
        return $value;
    }

    public function create(array $input, string $environment, int $defaultHours, int $actorId, string $actor, ?string $batchId = null): array
    {
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '환경을 확인해 주세요.']);
        $data = self::normalize($input, $defaultHours);
        $now = Clock::timestamp();
        $row = ['id' => bin2hex(random_bytes(16)), 'url_token' => Base64Url::encode(random_bytes(20)), 'environment' => $environment,
            'status' => Status::CREATED, 'product_name' => $data['product_name'], 'product_detail' => $data['product_detail'],
            'buyer_name' => $data['buyer_name'], 'phone' => $this->cipher->encrypt($data['phone']), 'phone_hash' => Phone::hash($data['phone'], $this->secret),
            'phone_mask' => Phone::mask($data['phone']), 'amount' => $data['amount'], 'expires_at' => $now + $data['expiry_hours'] * 3600,
            'batch_id' => $batchId, 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now, 'status_changed_at' => $now];
        for ($attempt = 1; ; $attempt++) {
            $row['number'] = RequestNumber::next($this->db(), $now);
            try {
                $this->db()->insert('initalk_requests', $row);
                break;
            } catch (DomainError $e) {
                // 같은 순간 두 관리자가 만들면 유일 제약에 걸린다. 번호만 다시 뽑는다.
                if ($attempt >= 5 || !preg_match('/UNIQUE|Duplicate/i', $e->getMessage())) throw $e;
            }
        }
        $this->events->record($row['id'], 'created', $actor, '주문번호 ' . $row['number']);
        return $this->find($row['id']);
    }

    public function find(string $id): array
    {
        $row = preg_match('/^[a-f0-9]{32}$/D', $id) ? $this->db()->selectOne('SELECT * FROM ' . $this->db()->table('initalk_requests') . ' WHERE id = ?', [$id]) : null;
        if ($row === null) throw DomainError::notFound('결제 요청을 찾을 수 없습니다.');
        return $this->decode($row);
    }

    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,40}$/D', $token)) return null;
        $row = $this->db()->selectOne('SELECT * FROM ' . $this->db()->table('initalk_requests') . ' WHERE url_token = ?', [$token]);
        return $row === null ? null : $this->decode($row);
    }

    /** 목록(search/recent)은 번호 원문 없이, 단건(find)은 복호화된 숫자 번호와 함께 돌려준다. */
    private function decode(array $row, bool $withPhone = true): array
    {
        foreach (['amount', 'expires_at', 'dispatch_count', 'refunded_amount', 'needs_review', 'created_by', 'created_at', 'updated_at', 'status_changed_at'] as $key) $row[$key] = (int) $row[$key];
        foreach (['last_dispatched_at', 'checkout_started_at', 'paid_at'] as $key) $row[$key] = $row[$key] === null ? null : (int) $row[$key];
        unset($row['phone_hash']);
        if ($withPhone) $row['phone'] = $row['phone'] === '' ? '' : $this->cipher->decrypt($row['phone']);
        else unset($row['phone']);
        $row['status_label'] = Status::label($row['status']);
        return $row;
    }

    /** @return array{items:list<array>,total:int,page:int,per_page:int} */
    public function search(array $filter, int $page = 1): array
    {
        $where = [];
        $params = [];
        if (in_array($filter['environment'] ?? '', ['test', 'live'], true)) { $where[] = 'environment = ?'; $params[] = $filter['environment']; }
        foreach (['from' => '>=', 'until' => '<='] as $key => $op) {
            $value = $filter[$key] ?? '';
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                $day = new \DateTimeImmutable($value . ($op === '>=' ? ' 00:00:00' : ' 23:59:59'), new \DateTimeZone('Asia/Seoul'));
                $where[] = 'created_at ' . $op . ' ?'; $params[] = $day->getTimestamp();
            }
        }
        $phone = is_string($filter['phone'] ?? null) ? preg_replace('/\D/', '', $filter['phone']) : '';
        if ($phone !== '') { $where[] = 'phone_hash = ?'; $params[] = Phone::hash($phone, $this->secret); }
        foreach (['buyer_name', 'product_name'] as $key) {
            $value = is_string($filter[$key] ?? null) ? trim($filter[$key]) : '';
            if ($value !== '') { $where[] = $key . ' LIKE ?'; $params[] = '%' . addcslashes($value, '%_\\') . '%'; }
        }
        $number = is_string($filter['number'] ?? null) ? trim($filter['number']) : '';
        if ($number !== '') { $where[] = 'number LIKE ?'; $params[] = addcslashes($number, '%_\\') . '%'; }
        $amount = is_scalar($filter['amount'] ?? null) ? str_replace(',', '', (string) $filter['amount']) : '';
        if (preg_match('/^\d{1,9}$/D', $amount)) { $where[] = 'amount = ?'; $params[] = (int) $amount; }
        if (isset(Status::LABELS[$filter['status'] ?? ''])) { $where[] = 'status = ?'; $params[] = $filter['status']; }
        $batch = is_string($filter['batch'] ?? null) ? $filter['batch'] : '';
        if (preg_match('/^[a-f0-9]{32}$/D', $batch)) { $where[] = 'batch_id = ?'; $params[] = $batch; }
        if (($filter['sendable'] ?? '') === '1') $where[] = "status IN ('created','waiting','expired')";
        if (($filter['sendable'] ?? '') === '0') $where[] = "status NOT IN ('created','waiting','expired')";
        $sql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $table = $this->db()->table('initalk_requests');
        $total = (int) ($this->db()->selectOne('SELECT COUNT(*) AS c FROM ' . $table . $sql, $params)['c'] ?? 0);
        $page = max(1, $page);
        $rows = $this->db()->select('SELECT * FROM ' . $table . $sql . ' ORDER BY created_at DESC, number DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        return ['items' => array_map(fn (array $row): array => $this->decode($row, false), $rows), 'total' => $total, 'page' => $page, 'per_page' => self::PER_PAGE];
    }

    public function counts(string $environment): array
    {
        $counts = array_fill_keys(array_keys(Status::LABELS), 0);
        foreach ($this->db()->select('SELECT status, COUNT(*) AS c FROM ' . $this->db()->table('initalk_requests') . ' WHERE environment = ? GROUP BY status', [$environment]) as $row) {
            if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    public function recent(string $environment, int $limit): array
    {
        $rows = $this->db()->select('SELECT * FROM ' . $this->db()->table('initalk_requests') . ' WHERE environment = ? ORDER BY created_at DESC, number DESC LIMIT ' . max(1, min(100, $limit)), [$environment]);
        return array_map(fn (array $row): array => $this->decode($row, false), $rows);
    }

    /** 같은 번호의 결제 완료 이력: 거래횟수·총 거래금액·최근 결제일. */
    public function customerSummary(string $phoneDigits, string $environment): array
    {
        $row = $this->db()->selectOne('SELECT COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total, MAX(paid_at) AS last FROM ' . $this->db()->table('initalk_requests')
            . " WHERE environment = ? AND phone_hash = ? AND status IN ('paid','refunded')", [$environment, Phone::hash($phoneDigits, $this->secret)]);
        return ['count' => (int) ($row['c'] ?? 0), 'total' => (int) ($row['total'] ?? 0), 'last_paid_at' => isset($row['last']) && $row['last'] !== null ? (int) $row['last'] : null];
    }

    /** @param list<string> $from */
    private function transition(string $id, array $from, string $to, array $extra, string $actor, string $type, string $note = ''): array
    {
        foreach (array_keys($extra) as $column) if (!in_array($column, self::EXTRA_COLUMNS, true)) throw DomainError::internal('허용되지 않은 컬럼 갱신입니다.');
        $now = Clock::timestamp();
        $params = ['id' => $id];
        $list = [];
        foreach (array_values($from) as $i => $status) { $params['from' . $i] = $status; $list[] = ':from' . $i; }
        $changed = $this->db()->update('initalk_requests', ['status' => $to, 'status_changed_at' => $now, 'updated_at' => $now] + $extra,
            'id = :id AND status IN (' . implode(', ', $list) . ')', $params);
        if ($changed !== 1) throw DomainError::validation(['status' => '상태가 변경되었습니다. 새로고침 후 확인해 주세요.']);
        $this->events->record($id, $type, $actor, $note);
        return $this->find($id);
    }

    public function cancel(string $id, string $actor): array
    {
        return $this->transition($id, [Status::CREATED, Status::WAITING, Status::EXPIRED], Status::CANCELLED, [], $actor, 'cancelled', '결제 전 취소');
    }

    public function markPaid(string $id, int $paidAt, string $transactionId, string $actor): array
    {
        return $this->transition($id, [Status::CREATED, Status::WAITING, Status::EXPIRED], Status::PAID,
            ['paid_at' => $paidAt, 'transaction_id' => mb_substr($transactionId, 0, 40)], $actor, 'paid', '승인 ' . $transactionId);
    }

    public function applyRefund(string $id, int $refundedTotal, string $actor, string $note): array
    {
        $request = $this->find($id);
        if (!Status::canRefund($request['status'])) throw DomainError::validation(['status' => '결제 완료 상태에서만 환불할 수 있습니다.']);
        if ($refundedTotal < $request['refunded_amount'] || $refundedTotal > $request['amount']) throw DomainError::validation(['refund' => '환불 누적액을 확인해 주세요.']);
        if ($refundedTotal === $request['amount']) return $this->transition($id, [Status::PAID], Status::REFUNDED, ['refunded_amount' => $refundedTotal], $actor, 'refund', $note);
        $changed = $this->db()->update('initalk_requests', ['refunded_amount' => $refundedTotal, 'updated_at' => Clock::timestamp()], 'id = :id AND status = :status', ['id' => $id, 'status' => Status::PAID]);
        if ($changed !== 1) throw DomainError::validation(['status' => '상태가 변경되었습니다. 새로고침 후 확인해 주세요.']);
        $this->events->record($id, 'refund', $actor, $note);
        return $this->find($id);
    }

    public function extend(string $id, int $hours, string $actor): array
    {
        $request = $this->find($id);
        $expiresAt = Clock::timestamp() + max(1, min(720, $hours)) * 3600;
        if ($request['status'] === Status::EXPIRED) {
            return $this->transition($id, [Status::EXPIRED], Status::CREATED, ['expires_at' => $expiresAt], $actor, 'extended', '결제기한 연장');
        }
        if (!Status::canPay($request['status'])) throw DomainError::validation(['status' => '기한을 연장할 수 없는 상태입니다.']);
        $changed = $this->db()->update('initalk_requests', ['expires_at' => $expiresAt, 'updated_at' => Clock::timestamp()], 'id = :id AND status IN (:s0, :s1)', ['id' => $id, 's0' => Status::CREATED, 's1' => Status::WAITING]);
        if ($changed !== 1) throw DomainError::validation(['status' => '상태가 변경되었습니다. 새로고침 후 확인해 주세요.']);
        $this->events->record($id, 'extended', $actor, '결제기한 연장');
        return $this->find($id);
    }

    public function recordDispatch(string $id, string $dispatchId, int $sequence, string $submission, string $actor): array
    {
        $now = Clock::timestamp();
        $this->db()->update('initalk_requests', ['dispatch_count' => $sequence, 'last_dispatch_id' => $dispatchId, 'last_dispatched_at' => $now, 'updated_at' => $now], 'id = :id', ['id' => $id]);
        $request = $this->find($id);
        if ($submission === 'accepted') {
            if ($request['status'] === Status::CREATED) return $this->transition($id, [Status::CREATED], Status::WAITING, [], $actor, 'dispatched', '알림톡 접수 ' . $sequence . '회');
            $this->events->record($id, 'dispatched', $actor, '알림톡 접수 ' . $sequence . '회');
        } else {
            $this->events->record($id, 'dispatch_failed', $actor, '알림톡 접수 실패(' . $submission . ') ' . $sequence . '회');
        }
        return $this->find($id);
    }

    public function touchCheckout(string $id, string $configRevision): void
    {
        $now = Clock::timestamp();
        $this->db()->update('initalk_requests', ['checkout_started_at' => $now, 'config_revision' => $configRevision, 'updated_at' => $now], 'id = :id', ['id' => $id]);
        $this->events->record($id, 'checkout', 'customer', '결제창 열기');
    }

    public function setReview(string $id, bool $review, string $actor, string $note): void
    {
        $this->db()->update('initalk_requests', ['needs_review' => $review ? 1 : 0, 'updated_at' => Clock::timestamp()], 'id = :id', ['id' => $id]);
        $this->events->record($id, $review ? 'review' : 'review_cleared', $actor, $note);
    }

    public function expire(int $limit = 200): int
    {
        $now = Clock::timestamp();
        $rows = $this->db()->select('SELECT id FROM ' . $this->db()->table('initalk_requests')
            . " WHERE status IN ('created','waiting') AND expires_at < ? AND (checkout_started_at IS NULL OR checkout_started_at < ?) ORDER BY expires_at LIMIT " . max(1, min(1000, $limit)),
            [$now, $now - self::CHECKOUT_GRACE]);
        $count = 0;
        foreach ($rows as $row) if ($this->expireOne($row['id'])) $count++;
        return $count;
    }

    /** 만료 조건을 다시 검사한 뒤 만료시킨다. 이미 다른 상태면 false. */
    public function expireOne(string $id): bool
    {
        $request = $this->find($id);
        $now = Clock::timestamp();
        if (!Status::canPay($request['status']) || $request['expires_at'] >= $now || ($request['checkout_started_at'] !== null && $request['checkout_started_at'] >= $now - self::CHECKOUT_GRACE)) return false;
        try {
            $this->transition($id, Status::OPEN, Status::EXPIRED, [], 'system', 'expired', '결제기한 경과');
            return true;
        } catch (DomainError $e) {
            return false;
        }
    }

    /** 종료된 지 90일이 지난 요청의 구매자명·번호를 지운다. 최대 100건. */
    public function purge(int $limit = 100): int
    {
        $rows = $this->db()->select('SELECT id FROM ' . $this->db()->table('initalk_requests')
            . " WHERE status IN ('paid','refunded','expired','cancelled') AND status_changed_at < ? AND phone <> '' LIMIT " . max(1, min(1000, $limit)), [Clock::timestamp() - self::RETENTION]);
        foreach ($rows as $row) {
            $this->db()->update('initalk_requests', ['phone' => '', 'phone_hash' => '', 'phone_mask' => '', 'buyer_name' => '', 'updated_at' => Clock::timestamp()], 'id = :id', ['id' => $row['id']]);
            $this->events->record($row['id'], 'purged', 'system', '보관 기간 만료 개인정보 정리');
        }
        return count($rows);
    }
}
