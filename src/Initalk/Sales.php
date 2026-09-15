<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

/** 확정 원장으로 만드는 매출·정산 집계. 영업일·공휴일 계산은 하지 않는다(문서에 명시). */
final class Sales
{
    private const TZ = 'Asia/Seoul';

    public function __construct(private Ledger $ledger)
    {
    }

    private static function seoul(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(self::TZ));
    }

    /** @return array{0:int,1:int} */
    public static function monthRange(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new \DateTimeZone(self::TZ));
        return [$start->getTimestamp(), $start->modify('last day of this month')->setTime(23, 59, 59)->getTimestamp()];
    }

    public function daily(string $environment, int $from, int $until): array
    {
        $days = [];
        foreach ($this->ledger->between($environment, $from, $until) as $row) {
            $date = self::seoul((int) $row['at'])->format('Y-m-d');
            $days[$date] ??= ['date' => $date, 'approve_count' => 0, 'approve_amount' => 0, 'refund_count' => 0, 'refund_amount' => 0, 'net' => 0];
            if ($row['kind'] === 'approve') {
                $days[$date]['approve_count']++;
                $days[$date]['approve_amount'] += (int) $row['amount'];
                $days[$date]['net'] += (int) $row['amount'];
            } else {
                $days[$date]['refund_count']++;
                $days[$date]['refund_amount'] += (int) $row['amount'];
                $days[$date]['net'] -= (int) $row['amount'];
            }
        }
        ksort($days);
        return array_values($days);
    }

    public function summary(string $environment, int $from, int $until): array
    {
        $summary = ['approve_count' => 0, 'approve_amount' => 0, 'refund_count' => 0, 'refund_amount' => 0, 'net' => 0];
        foreach ($this->daily($environment, $from, $until) as $day) foreach ($summary as $key => $value) $summary[$key] += $day[$key];
        return $summary;
    }

    /** 지급예정일(승인·환불일 + N일) 기준 순액. 해당 월에 지급예정일이 있는 원장만. */
    public function calendar(string $environment, int $year, int $month, int $settlementDays): array
    {
        [$from, $until] = self::monthRange($year, $month);
        $shift = $settlementDays * 86400;
        $calendar = [];
        foreach ($this->ledger->between($environment, $from - $shift, $until - $shift) as $row) {
            $date = self::seoul((int) $row['at'] + $shift)->format('Y-m-d');
            $calendar[$date] = ($calendar[$date] ?? 0) + ($row['kind'] === 'approve' ? (int) $row['amount'] : -(int) $row['amount']);
        }
        ksort($calendar);
        return $calendar;
    }

    /** 아직 지급예정일이 오지 않은 원장의 순액. */
    public function expectedPayout(string $environment, int $settlementDays, int $now): int
    {
        $total = 0;
        foreach ($this->ledger->between($environment, $now - $settlementDays * 86400 + 1, $now + 366 * 86400) as $row) {
            $total += $row['kind'] === 'approve' ? (int) $row['amount'] : -(int) $row['amount'];
        }
        return $total;
    }

    public function csv(string $environment, int $from, int $until): string
    {
        $lines = ["\xEF\xBB\xBF일시,주문번호,상품명,구매자명,휴대폰,구분,금액,참조"];
        foreach ($this->ledger->between($environment, $from, $until) as $row) {
            $cells = [self::seoul((int) $row['at'])->format('Y-m-d H:i'), $row['number'], $row['product_name'], $row['buyer_name'] !== '' ? $row['buyer_name'] : '보관 만료',
                $row['phone_mask'] !== '' ? $row['phone_mask'] : '보관 만료', $row['kind'] === 'approve' ? '승인' : '환불',
                (string) ($row['kind'] === 'approve' ? (int) $row['amount'] : -(int) $row['amount']), $row['reference']];
            $lines[] = implode(',', array_map(static fn (string $cell): string => preg_match('/[",\r\n]/', $cell) ? '"' . str_replace('"', '""', $cell) . '"' : $cell, $cells));
        }
        return implode("\n", $lines) . "\n";
    }
}
