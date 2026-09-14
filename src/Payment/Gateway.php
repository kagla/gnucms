<?php

declare(strict_types=1);

namespace GnuCms\Payment;

/** 결제 플러그인의 서버 전용 계약. 금액은 모두 정수 원(KRW)이다. */
interface Gateway
{
    public function id(): string;
    public function label(): string;
    public function available(string $environment): bool;
    public function configuration(string $environment): array;
    public function checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device = 'web'): array;

    /** 브라우저 인증 결과로 PG 서버에 승인을 요청한다. */
    public function complete(array $order, array $callback): void;
    public function fetch(array $order): array;
    public function cancel(array $order, int $amount, int $remaining, string $reason, string $key): array;
}
