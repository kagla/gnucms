<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

use GnuCms\Error\DomainError;
use GnuCms\Support\QrCode;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    public function testReedSolomonMatchesTheStandardExample(): void
    {
        // ISO/IEC 18004 1-M 예제("HELLO WORLD")의 데이터 코드워드와 오류정정 코드워드.
        $data = [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17];
        self::assertSame([196, 35, 39, 119, 235, 215, 231, 226, 93, 23], QrCode::reedSolomon($data, 10));
    }

    public function testFormatInformationIsBchEncodedAndMasked(): void
    {
        self::assertSame(0x5412, QrCode::formatBits(0));
        self::assertSame(0b101000100100101, QrCode::formatBits(1));
        for ($mask = 0; $mask < 8; $mask++) {
            $value = QrCode::formatBits($mask) ^ 0x5412;
            self::assertSame($mask, $value >> 10, 'data bits');
            for ($i = 14; $i >= 10; $i--) if (($value >> $i) & 1) $value ^= 0x537 << ($i - 10);
            self::assertSame(0, $value & 0x3FF, 'BCH remainder for mask ' . $mask);
        }
    }

    public function testVersionSelectionAndCapacity(): void
    {
        self::assertSame([14, 26, 42, 62, 84, 106, 122, 152, 180, 213], array_map(QrCode::capacity(...), range(1, 10)));
        self::assertSame(1, QrCode::version(str_repeat('a', 14)));
        self::assertSame(2, QrCode::version(str_repeat('a', 15)));
        self::assertSame(10, QrCode::version(str_repeat('a', 213)));
        $this->expectException(DomainError::class);
        QrCode::version(str_repeat('a', 214));
    }

    public function testMatrixHasFunctionPatternsAndIsDeterministic(): void
    {
        $text = 'https://shop.example.test/pay/abcdefghijklmnopqrstuvwxyz0';
        $matrix = QrCode::matrix($text);
        $size = count($matrix);
        self::assertSame(17 + 4 * QrCode::version($text), $size);
        foreach ($matrix as $row) self::assertCount($size, $row);
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
            self::assertTrue($matrix[$r][$c]);
            self::assertTrue($matrix[$r + 3][$c + 3]);
            self::assertFalse($matrix[$r + 1][$c + 1]);
            self::assertTrue($matrix[$r + 6][$c + 6]);
        }
        self::assertFalse($matrix[7][7]);
        for ($i = 8; $i < $size - 8; $i++) {
            self::assertSame($i % 2 === 0, $matrix[6][$i], 'timing row');
            self::assertSame($i % 2 === 0, $matrix[$i][6], 'timing column');
        }
        self::assertTrue($matrix[4 * QrCode::version($text) + 9][8], 'dark module');
        self::assertSame($matrix, QrCode::matrix($text));
        $svg = QrCode::svg($text);
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        self::assertStringContainsString('<path d="M', $svg);
        $dim = ($size + 8) * 4;
        self::assertStringContainsString('viewBox="0 0 ' . $dim . ' ' . $dim . '"', $svg);
        $this->expectException(DomainError::class);
        QrCode::matrix('');
    }

    public function testVersionSevenAndAboveCarryVersionInformation(): void
    {
        $matrix = QrCode::matrix(str_repeat('https://shop.example.test/pay/', 5)); // 150 bytes → 버전 8
        $size = count($matrix);
        self::assertSame(49, $size);
        // 버전 8 정보 0x085BC: 비트 i는 (행 i/3, 열 size-11+i%3)와 그 전치 위치에 놓인다.
        for ($i = 0; $i < 18; $i++) {
            $bit = ((0x085BC >> $i) & 1) === 1;
            self::assertSame($bit, $matrix[intdiv($i, 3)][$size - 11 + $i % 3], 'version bit ' . $i);
            self::assertSame($bit, $matrix[$size - 11 + $i % 3][intdiv($i, 3)], 'version bit mirror ' . $i);
        }
    }

    public function testPngRoundTripsThroughAnExternalDecoderWhenAvailable(): void
    {
        if (!function_exists('imagecreatetruecolor')) self::markTestSkipped('GD 없음');
        $python = getenv('GNUCMS_QR_PYTHON') ?: 'python3';
        $text = 'https://shop.example.test/pay/Zm9vYmFyYmF6cXV4cXV1eA';
        $file = tempnam(sys_get_temp_dir(), 'gnucms-qr-') . '.png';
        file_put_contents($file, QrCode::png($text));
        try {
            $process = proc_open([$python, __DIR__ . '/qr_decode.py', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) self::markTestSkipped('python3 없음');
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]);
            $code = proc_close($process);
            if ($code === 2 || $code === 127) self::markTestSkipped('OpenCV 디코더 없음 — 휴대폰 스캔으로 확인');
            self::assertSame(0, $code);
            self::assertSame($text, $out);
        } finally {
            @unlink($file);
        }
    }
}
