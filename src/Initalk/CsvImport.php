<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/** CSV 일괄등록: 파싱·검증 → 세션 미리보기 → 확정 생성(+즉시 발송). */
final class CsvImport
{
    public const MAX_BYTES = 1048576;
    public const MAX_ROWS = 500;
    public const TTL = 600;
    public const COLUMNS = ['상품명' => 'product_name', '상품상세' => 'product_detail', '구매자명' => 'buyer_name', '휴대폰번호' => 'phone', '금액' => 'amount', '결제기한(시간)' => 'expiry_hours'];
    private const REQUIRED = ['product_name', 'buyer_name', 'phone', 'amount'];

    public function __construct(private App $app, private ?Requests $requests, private ?Notifier $notifier, private ?Settings $settings)
    {
    }

    public static function sample(): string
    {
        return "\xEF\xBB\xBF상품명,상품상세,구매자명,휴대폰번호,금액,결제기한(시간)\n"
            . "플로럴 핸드크림 30ml,향기 좋은 핸드크림,김이니,010-2345-7891,15800,48\n"
            . "9월 수강료,\"수학, 영어\",홍길동,010-1111-2222,120000,\n";
    }

    /** @return array{rows:list<array>,errors:list<string>,total:int} */
    public function parse(string $contents, int $defaultHours): array
    {
        if ($contents === '') return ['rows' => [], 'errors' => ['파일이 비어 있습니다.'], 'total' => 0];
        if (strlen($contents) > self::MAX_BYTES) return ['rows' => [], 'errors' => ['파일은 1MB 이하여야 합니다.'], 'total' => 0];
        if (str_starts_with($contents, "\xEF\xBB\xBF")) $contents = substr($contents, 3);
        if (!mb_check_encoding($contents, 'UTF-8')) $contents = (string) mb_convert_encoding($contents, 'UTF-8', 'CP949');
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        while ($lines !== [] && trim((string) end($lines)) === '') array_pop($lines);
        if ($lines === []) return ['rows' => [], 'errors' => ['파일이 비어 있습니다.'], 'total' => 0];
        $header = array_map(static fn ($cell): string => trim((string) $cell), str_getcsv((string) array_shift($lines), ',', '"', ''));
        $columns = [];
        foreach ($header as $index => $name) if (isset(self::COLUMNS[$name])) $columns[self::COLUMNS[$name]] = $index;
        $missing = array_diff(self::REQUIRED, array_keys($columns));
        if ($missing !== []) {
            $labels = array_map(static fn (string $key): string => array_search($key, self::COLUMNS, true), $missing);
            return ['rows' => [], 'errors' => ['필수 열이 없습니다: ' . implode(', ', $labels) . '. 샘플 파일의 머리글을 사용해 주세요.'], 'total' => 0];
        }
        if (count($lines) > self::MAX_ROWS) return ['rows' => [], 'errors' => ['한 번에 최대 500행까지 등록할 수 있습니다.'], 'total' => count($lines)];
        $rows = [];
        $errors = [];
        $total = 0;
        foreach ($lines as $offset => $line) {
            if (trim($line) === '') continue;
            $total++;
            $cells = str_getcsv($line, ',', '"', '');
            $input = [];
            foreach ($columns as $key => $index) $input[$key] = isset($cells[$index]) ? trim((string) $cells[$index]) : '';
            try {
                $rows[] = ['line' => $offset + 2] + Requests::normalize($input, $defaultHours);
            } catch (DomainError $e) {
                $errors[] = ($offset + 2) . '행: ' . implode(' ', array_values($e->details() ?: [$e->getMessage()]));
            }
        }
        return ['rows' => $rows, 'errors' => $errors, 'total' => $total];
    }

    public function remember(array $parsed, string $filename, bool $sendNow): string
    {
        $token = bin2hex(random_bytes(16));
        $pending = $this->prune();
        if (count($pending) >= 3) array_shift($pending);
        $pending[$token] = ['expires' => Clock::timestamp() + self::TTL, 'filename' => mb_substr($filename, 0, 200), 'send' => $sendNow, 'rows' => $parsed['rows']];
        $_SESSION['initalk_imports'] = $pending;
        return $token;
    }

    public function pending(string $token): ?array
    {
        $entry = $this->prune()[$token] ?? null;
        return is_array($entry) ? $entry : null;
    }

    /** 기한이 지난 미리보기를 세션에서 지운다. 원문 휴대폰 번호가 TTL을 넘겨 남지 않게 조회할 때마다 돈다. */
    private function prune(): array
    {
        $pending = is_array($_SESSION['initalk_imports'] ?? null) ? $_SESSION['initalk_imports'] : [];
        foreach ($pending as $key => $entry) if (!is_array($entry) || ($entry['expires'] ?? 0) < Clock::timestamp()) unset($pending[$key]);
        $_SESSION['initalk_imports'] = $pending;
        return $pending;
    }

    /** @return array{batch_id:string,created:int,failed:int,sent:int,errors:list<string>} */
    public function confirm(string $token, string $environment, int $actorId, string $actor): array
    {
        $pending = $this->pending($token);
        unset($_SESSION['initalk_imports'][$token]);
        if ($pending === null) throw DomainError::validation(['import' => '미리보기가 만료되었습니다. 파일을 다시 올려 주세요.']);
        $requests = $this->requests ?? throw DomainError::internal('요청 저장소가 없습니다.');
        $config = ($this->settings ?? throw DomainError::internal('설정이 없습니다.'))->read();
        $batchId = bin2hex(random_bytes(16));
        $db = $this->app->db();
        $db->insert('initalk_batches', ['id' => $batchId, 'filename' => $pending['filename'], 'total' => count($pending['rows']), 'created' => 0, 'failed' => 0,
            'errors' => '[]', 'created_by' => $actorId, 'created_at' => Clock::timestamp()]);
        $created = [];
        $errors = [];
        foreach ($pending['rows'] as $row) {
            $input = ['product_name' => $row['product_name'], 'product_detail' => $row['product_detail'], 'buyer_name' => $row['buyer_name'],
                'phone' => $row['phone'], 'amount' => (string) $row['amount'], 'expiry_hours' => (string) $row['expiry_hours']];
            try {
                $created[] = $requests->create($input, $environment, $config['expiry_hours'], $actorId, $actor, $batchId)['id'];
            } catch (DomainError $e) {
                $errors[] = $row['line'] . '행: ' . ($e->status() >= 500 ? '생성하지 못했습니다.' : implode(' ', array_values($e->details() ?: [$e->getMessage()])));
            }
        }
        $sent = 0;
        if ($pending['send'] && $created !== [] && $this->notifier !== null) {
            $summary = $this->notifier->sendMany($created, $actor);
            $sent = $summary['sent'];
            foreach ($summary['errors'] as $id => $message) $errors[] = '발송 실패 ' . substr($id, 0, 8) . '…: ' . $message;
        }
        $db->update('initalk_batches', ['created' => count($created), 'failed' => count($pending['rows']) - count($created), 'errors' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            'id = :id', ['id' => $batchId]);
        return ['batch_id' => $batchId, 'created' => count($created), 'failed' => count($pending['rows']) - count($created), 'sent' => $sent, 'errors' => $errors];
    }
}
