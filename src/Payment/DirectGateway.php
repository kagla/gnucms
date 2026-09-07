<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

abstract class DirectGateway implements Gateway
{
    protected Journal $journal;
    public function __construct(public readonly Settings $settings, protected Transport $http = new StreamTransport()) { $this->journal = new Journal($settings); }
    public function id(): string { return $this->settings->provider; }
    public function label(): string { return Settings::PROVIDERS[$this->id()]; }
    public function available(string $environment): bool { return $this->settings->available($environment); }
    public function configuration(string $environment): array { $this->settings->requireEnabled($environment); return $this->settings->summary($environment); }

    protected function credentials(array $order): array
    {
        if (($order['provider'] ?? '') !== $this->id() || !preg_match('/^[a-f0-9]{32}$/D', $order['id'] ?? '')) throw DomainError::validation(['order' => '주문 결제사를 확인해 주세요.']);
        $config = $this->settings->credentials($order['config_revision']);
        if ($config['environment'] !== $order['environment']) throw DomainError::validation(['environment' => '주문 결제 환경이 다릅니다.']);
        $this->settings->requireEnabled($config['environment']);
        return $config;
    }

    protected function prepare(array $order, string $returnUrl, string $callbackUrl): array
    {
        $config = $this->credentials($order);
        foreach ([$returnUrl, $callbackUrl] as $url) if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_USER) !== null || preg_match('/[\r\n]/', $url)) throw DomainError::validation(['url' => '사이트 주소를 공개 HTTPS 주소로 설정해 주세요.']);
        $this->journal->change($order['id'], static function (array $state) use ($order): array {
            if (in_array($state['approval'] ?? '', ['pending', 'confirmed'], true)) throw DomainError::validation(['payment' => '이미 요청한 결제 결과를 먼저 확인해 주세요.']);
            return $state + ['approval' => 'ready', 'created_at' => Clock::timestamp(), 'revision' => $order['config_revision'], 'refunds' => []];
        });
        return $config;
    }

    /** 인증 토큰의 형식과 주문 연결을 검사한 후에만 요청을 기록한다. */
    abstract protected function validateCallback(array $config, array $order, array $callback): void;
    abstract protected function approve(array $config, array $order, array $callback): array;
    abstract protected function query(array $config, array $order, array $state): array;
    abstract protected function refund(array $config, array $order, array $state, int $amount, int $remaining, string $reason, string $key): array;

    public function complete(array $order, array $callback): void
    {
        $config = $this->credentials($order);
        $this->validateCallback($config, $order, $callback);
        $send = false;
        $this->journal->change($order['id'], static function (array $state) use (&$send): array {
            if ($state === []) throw DomainError::forbidden('결제 준비 기록이 없습니다.');
            if (in_array($state['approval'], ['pending', 'confirmed'], true)) return $state;
            $send = true; $state['approval'] = 'pending';
            return $state;
        });
        if (!$send) return;
        // 통신/DB 실패 시 pending을 남겨 새 승인·재승인을 막는다.
        $payment = $this->approve($config, $order, $callback);
        $this->journal->change($order['id'], static function (array $state) use ($payment): array {
            $state['approval'] = 'confirmed'; $state['approved'] = $payment;
            return $state;
        });
    }

    public function fetch(array $order): array
    {
        $config = $this->credentials($order);
        $state = $this->journal->read($order['id']);
        if (!in_array($state['approval'] ?? '', ['pending', 'confirmed'], true)) return ['status' => 'NOT_FOUND'];
        $payment = $this->query($config, $order, $state);
        $known = [];
        foreach ($state['refunds'] ?? [] as $refund) if (($refund['status'] ?? '') === 'succeeded') $known[$refund['result']['id']] = $refund['result'];
        if (!isset($payment['cancellations'])) {
            $payment['cancellations'] = array_values($known);
        } else {
            foreach ($payment['cancellations'] as &$cancel) {
                $cancel['reason'] = $known[$cancel['id']]['reason'] ?? '';
            }
            unset($cancel);
        }
        // 미확정 취소는 조회 결과가 같아도 임의로 기존 신청과 연결하지 않는다.
        $payment['open_cancellations'] = count(array_filter($state['refunds'] ?? [], static fn ($r) => $r['status'] === 'pending'));
        $payment['valid'] = ($payment['valid'] ?? false) && array_sum(array_column($payment['cancellations'], 'amount')) === ($payment['cancelled'] ?? -1);
        $seen = [];
        foreach ($payment['cancellations'] as $cancel) {
            if (!is_string($cancel['id'] ?? null) || $cancel['id'] === '' || strlen($cancel['id']) > 100 || isset($seen[$cancel['id']])
                || !is_int($cancel['amount'] ?? null) || $cancel['amount'] < 1 || !is_int($cancel['at'] ?? null) || $cancel['at'] < 1) $payment['valid'] = false;
            $seen[$cancel['id']] = true;
        }
        return $payment;
    }

    public function cancel(array $order, int $amount, int $remaining, string $reason, string $key): array
    {
        $config = $this->credentials($order);
        if ($amount < 1 || $amount > $remaining || $remaining > (int) $order['total'] || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) throw DomainError::validation(['refund' => '환불 금액과 요청 키를 확인해 주세요.']);
        $send = false;
        $state = $this->journal->change($order['id'], static function (array $state) use ($key, $amount, $remaining, $reason, &$send): array {
            if (isset($state['refunds'][$key])) {
                $old = $state['refunds'][$key];
                if ($old['amount'] !== $amount || $old['remaining'] !== $remaining || $old['reason'] !== $reason) throw DomainError::validation(['refund' => '같은 요청 키의 환불 내용이 다릅니다.']);
                return $state;
            }
            foreach ($state['refunds'] ?? [] as $refund) if ($refund['status'] === 'pending') throw DomainError::validation(['refund' => '기존 환불을 PG에서 확인해 주세요.']);
            $state['refunds'][$key] = ['status' => 'pending', 'amount' => $amount, 'remaining' => $remaining, 'reason' => $reason, 'at' => Clock::timestamp()];
            $send = true;
            return $state;
        });
        if (!$send) {
            if ($state['refunds'][$key]['status'] === 'succeeded') return $state['refunds'][$key]['result'];
            throw DomainError::serviceUnavailable('환불 결과가 불확실해 자동 재전송을 중지했습니다. PG에서 처리 여부를 확인해 주세요.');
        }
        $result = $this->refund($config, $order, $state, $amount, $remaining, $reason, $key);
        $result['reason'] = $reason;
        $this->journal->change($order['id'], static function (array $state) use ($key, $result): array {
            $state['refunds'][$key]['status'] = 'succeeded'; $state['refunds'][$key]['result'] = $result;
            return $state;
        });
        return $result;
    }

    /** 관리자 대조: PG 조회에서 확인한 취소만 기존 보류 요청에 연결한다. */
    public function confirmRefund(array $order, string $key, string $reference): void
    {
        $config = $this->credentials($order);
        $state = $this->journal->read($order['id']);
        $pending = $state['refunds'][$key] ?? null;
        if ($pending === null || $pending['status'] !== 'pending') return;
        $payment = $this->query($config, $order, $state);
        if (!($payment['valid'] ?? false)) throw DomainError::validation(['refund' => 'PG 거래 정보가 주문과 일치하지 않습니다.']);
        $matched = null;
        foreach ($payment['cancellations'] ?? [] as $cancel) if ($cancel['id'] === $reference && $cancel['amount'] === $pending['amount']) $matched = $cancel;
        if ($matched === null) throw DomainError::validation(['refund' => 'PG 조회에서 동일한 취소 ID와 금액을 확인하지 못했습니다.']);
        $this->journal->change($order['id'], static function (array $state) use ($key, $matched): array {
            foreach ($state['refunds'] as $otherKey => $refund) if ($otherKey !== $key && ($refund['result']['id'] ?? '') === $matched['id']) throw DomainError::validation(['refund' => '이미 다른 환불에 연결된 취소입니다.']);
            $state['refunds'][$key]['status'] = 'succeeded';
            // 쇼핑몰 관리자 작업이 해당 신청을 명시적으로 연결한다.
            $state['refunds'][$key]['result'] = array_replace($matched, ['reason' => '']);
            return $state;
        });
    }

    /** 운영자가 PG 미처리를 확인한 2시간 경과 요청만 종료할 수 있다. */
    public function confirmUnprocessedRefund(array $order, string $key): void
    {
        $state = $this->journal->read($order['id']);
        $refund = $state['refunds'][$key] ?? null;
        if ($refund === null || $refund['status'] !== 'pending') return;
        if (Clock::timestamp() - $refund['at'] <= 7200) throw DomainError::validation(['refund' => 'PG 처리 여부를 확인한 뒤 2시간 경과 요청을 종료해 주세요.']);
        $payment = $this->fetch($order);
        if (!($payment['valid'] ?? false) || (int) $order['total'] - $payment['cancelled'] !== $refund['remaining']) throw DomainError::validation(['refund' => 'PG 잔액이 변경되었습니다. 확정 취소를 연결해 주세요.']);
        $this->journal->change($order['id'], static function (array $state) use ($key): array {
            if ($state['refunds'][$key]['status'] === 'pending') $state['refunds'][$key]['status'] = 'failed';
            return $state;
        });
    }

    protected function request(string $url, array $body, bool $form = false, array $headers = []): array
    {
        $response = $this->http->request('POST', $url, $headers + ['Content-Type' => $form ? 'application/x-www-form-urlencoded;charset=utf-8' : 'application/json'], $body);
        if ($response['status'] !== 200) throw DomainError::serviceUnavailable('PG 응답을 확인하지 못했습니다. 결제 상태를 확인해 주세요.');
        return $response['body'];
    }

    protected static function value(array $input, string $key, int $max = 16384): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value)) throw DomainError::validation(['callback' => 'PG 응답 항목을 확인해 주세요.']);
        return $value;
    }

    protected static function amount(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]{1,12}$/D', (string) $value)) return -1;
        return (int) $value;
    }

    protected static function date(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^[0-9]{14}$/D', $value)) return 0;
        $date = \DateTimeImmutable::createFromFormat('!YmdHis', $value, new \DateTimeZone('Asia/Seoul'));
        return $date !== false && $date->format('YmdHis') === $value ? $date->getTimestamp() : 0;
    }

    protected static function now(): string { return (new \DateTimeImmutable('@' . Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('YmdHis'); }
}
