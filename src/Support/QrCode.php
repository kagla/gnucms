<?php

declare(strict_types=1);

namespace GnuCms\Support;

use GnuCms\Error\DomainError;

/**
 * 외부 의존성 없는 QR 인코더. 바이트 모드, 오류정정 M, 버전 1~10, ISO/IEC 18004 배치·마스크 규칙.
 * 결제 링크(100자 이내)를 넣는 용도이며 카나·한자 모드 등은 지원하지 않는다.
 */
final class QrCode
{
    private const EC_LEVEL = 0; // M
    /** 버전 => [총 코드워드, 블록당 EC 코드워드, [[블록 수, 블록당 데이터 코드워드], …]] */
    private const BLOCKS = [
        1 => [26, 10, [[1, 16]]], 2 => [44, 16, [[1, 28]]], 3 => [70, 26, [[1, 44]]], 4 => [100, 18, [[2, 32]]], 5 => [134, 24, [[2, 43]]],
        6 => [172, 16, [[4, 27]]], 7 => [196, 18, [[4, 31]]], 8 => [242, 22, [[2, 38], [2, 39]]], 9 => [292, 22, [[3, 36], [2, 37]]], 10 => [346, 26, [[4, 43], [1, 44]]],
    ];
    private const ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    private const VERSION_INFO = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];
    private static ?array $exp = null;
    private static ?array $log = null;

    public static function capacity(int $version): int
    {
        $data = 0;
        foreach (self::BLOCKS[$version][2] as [$count, $length]) $data += $count * $length;
        return intdiv($data * 8 - 4 - ($version >= 10 ? 16 : 8), 8);
    }

    public static function version(string $text): int
    {
        for ($version = 1; $version <= 10; $version++) if (strlen($text) <= self::capacity($version)) return $version;
        throw DomainError::validation(['qr' => 'QR 코드에 넣기에는 너무 긴 내용입니다(최대 213바이트).']);
    }

    /** @return list<list<bool>> */
    public static function matrix(string $text): array
    {
        if ($text === '') throw DomainError::validation(['qr' => 'QR 코드 내용이 비어 있습니다.']);
        $version = self::version($text);
        $size = 17 + 4 * $version;
        [$modules, $reserved] = self::functionPatterns($version, $size);
        self::placeData($modules, $reserved, self::codewords($text, $version), $size);
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) if (!$reserved[$y][$x] && self::maskBit($mask, $y, $x)) $candidate[$y][$x] = !$candidate[$y][$x];
            self::writeFormat($candidate, $mask, $size);
            if ($version >= 7) self::writeVersion($candidate, $version, $size);
            $score = self::penalty($candidate, $size);
            if ($score < $bestScore) { $bestScore = $score; $best = $candidate; }
        }
        return $best;
    }

    public static function svg(string $text, int $module = 4, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $dim = ($size + 2 * $quiet) * $module;
        $path = '';
        foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
            if ($dark) $path .= 'M' . (($x + $quiet) * $module) . ' ' . (($y + $quiet) * $module) . 'h' . $module . 'v' . $module . 'h-' . $module . 'z';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR 코드">'
            . '<rect width="100%" height="100%" fill="#ffffff"/><path d="' . $path . '" fill="#000000"/></svg>';
    }

    public static function png(string $text, int $module = 8, int $quiet = 4): string
    {
        if (!function_exists('imagecreatetruecolor')) throw DomainError::internal('PNG 생성에는 GD 확장이 필요합니다.');
        $matrix = self::matrix($text);
        $size = count($matrix);
        $dim = ($size + 2 * $quiet) * $module;
        $image = imagecreatetruecolor($dim, $dim);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $dim - 1, $dim - 1, $white);
        foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
            if ($dark) imagefilledrectangle($image, ($x + $quiet) * $module, ($y + $quiet) * $module, ($x + $quiet + 1) * $module - 1, ($y + $quiet + 1) * $module - 1, $black);
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /** 데이터 비트 → 패딩 → 블록 분할 → RS → 인터리브. @return list<int> */
    private static function codewords(string $text, int $version): array
    {
        [, $ecCount, $blocks] = self::BLOCKS[$version];
        $dataCount = 0;
        foreach ($blocks as [$count, $length]) $dataCount += $count * $length;
        $bits = '0100' . str_pad(decbin(strlen($text)), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $bits .= str_repeat('0', min(4, $dataCount * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        $data = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($data) < $dataCount; $pad++) $data[] = $pad % 2 === 0 ? 0xEC : 0x11;
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($blocks as [$count, $length]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $offset, $length);
                $offset += $length;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecCount);
            }
        }
        $result = [];
        $longest = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $longest; $i++) foreach ($dataBlocks as $block) if (isset($block[$i])) $result[] = $block[$i];
        for ($i = 0; $i < $ecCount; $i++) foreach ($ecBlocks as $block) $result[] = $block[$i];
        return $result;
    }

    private static function tables(): void
    {
        if (self::$exp !== null) return;
        $exp = [];
        $log = [];
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function multiply(int $a, int $b): int
    {
        return $a === 0 || $b === 0 ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** GF(256) 리드-솔로몬 오류정정 코드워드. @param list<int> $data @return list<int> */
    public static function reedSolomon(array $data, int $ecCount): array
    {
        self::tables();
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= self::multiply($coefficient, self::$exp[$i]);
            }
            $generator = $next;
        }
        $remainder = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($remainder);
            $remainder[] = 0;
            foreach ($generator as $j => $coefficient) {
                if ($j > 0) $remainder[$j - 1] ^= self::multiply($coefficient, $factor);
            }
        }
        return $remainder;
    }

    /** @return array{0:list<list<bool>>,1:list<list<bool>>} 모듈과 기능 패턴 예약 표 */
    private static function functionPatterns(int $version, int $size): array
    {
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));
        $set = static function (int $y, int $x, bool $dark) use (&$modules, &$reserved, $size): void {
            if ($y < 0 || $x < 0 || $y >= $size || $x >= $size) return;
            $modules[$y][$x] = $dark;
            $reserved[$y][$x] = true;
        };
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$row, $col]) {
            for ($r = -1; $r <= 7; $r++) for ($c = -1; $c <= 7; $c++) {
                $inside = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
                $set($row + $r, $col + $c, $inside && max(abs($r - 3), abs($c - 3)) !== 2);
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        $positions = self::ALIGNMENT[$version];
        $last = $positions === [] ? -1 : $positions[count($positions) - 1];
        foreach ($positions as $row) foreach ($positions as $col) {
            if (($row === 6 && $col === 6) || ($row === 6 && $col === $last) || ($row === $last && $col === 6)) continue;
            for ($r = -2; $r <= 2; $r++) for ($c = -2; $c <= 2; $c++) $set($row + $r, $col + $c, max(abs($r), abs($c)) !== 1);
        }
        // 형식 정보 자리(값은 마스크 선택 후 기록) + 어두운 모듈
        for ($i = 0; $i <= 8; $i++) { if ($i === 6) continue; $set(8, $i, false); $set($i, 8, false); } // 6은 타이밍 패턴
        for ($i = 0; $i < 8; $i++) { $set(8, $size - 1 - $i, false); $set($size - 1 - $i, 8, false); }
        $set($size - 8, 8, true);
        if ($version >= 7) {
            for ($i = 0; $i < 18; $i++) {
                $set(intdiv($i, 3), $size - 11 + $i % 3, false);
                $set($size - 11 + $i % 3, intdiv($i, 3), false);
            }
        }
        return [$modules, $reserved];
    }

    /** 오른쪽 아래부터 두 열씩 지그재그로 데이터 비트를 놓는다. 6열은 건너뛴다. */
    private static function placeData(array &$modules, array $reserved, array $codewords, int $size): void
    {
        $bits = '';
        foreach ($codewords as $codeword) $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        $index = 0;
        $upward = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;
            for ($k = 0; $k < $size; $k++) {
                $row = $upward ? $size - 1 - $k : $k;
                foreach ([$col, $col - 1] as $x) {
                    if ($reserved[$row][$x]) continue;
                    $modules[$row][$x] = $index < strlen($bits) && $bits[$index] === '1';
                    $index++;
                }
            }
            $upward = !$upward;
        }
    }

    private static function maskBit(int $mask, int $i, int $j): bool
    {
        return match ($mask) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => ($i * $j) % 2 + ($i * $j) % 3 === 0,
            6 => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0,
            7 => (($i + $j) % 2 + ($i * $j) % 3) % 2 === 0,
        };
    }

    /** 15비트 형식 정보: (EC 2비트 | 마스크 3비트) + BCH(15,5) 나머지, 0x5412로 XOR. */
    public static function formatBits(int $mask): int
    {
        $data = (self::EC_LEVEL << 3) | $mask;
        $remainder = $data << 10;
        for ($i = 14; $i >= 10; $i--) if (($remainder >> $i) & 1) $remainder ^= 0x537 << ($i - 10);
        return (($data << 10) | $remainder) ^ 0x5412;
    }

    private static function writeFormat(array &$m, int $mask, int $size): void
    {
        $bits = self::formatBits($mask);
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $m[$i][8] = $bit($i);
        $m[7][8] = $bit(6);
        $m[8][8] = $bit(7);
        $m[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) $m[8][14 - $i] = $bit($i);
        for ($i = 0; $i < 8; $i++) $m[8][$size - 1 - $i] = $bit($i);
        for ($i = 8; $i < 15; $i++) $m[$size - 15 + $i][8] = $bit($i);
        $m[$size - 8][8] = true;
    }

    private static function writeVersion(array &$m, int $version, int $size): void
    {
        $bits = self::VERSION_INFO[$version];
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $m[intdiv($i, 3)][$size - 11 + $i % 3] = $dark;
            $m[$size - 11 + $i % 3][intdiv($i, 3)] = $dark;
        }
    }

    /** ISO 18004 마스크 벌점 4개 규칙(N1=3, N2=3, N3=40, N4=10). */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        $lines = [];
        for ($y = 0; $y < $size; $y++) {
            $row = '';
            $col = '';
            for ($x = 0; $x < $size; $x++) {
                $row .= $m[$y][$x] ? '1' : '0';
                $col .= $m[$x][$y] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $col;
        }
        foreach ($lines as $line) {
            preg_match_all('/(0{5,}|1{5,})/', $line, $runs);
            foreach ($runs[0] as $run) $score += 3 + strlen($run) - 5;
            $score += 40 * (substr_count($line, '00001011101') + substr_count($line, '10111010000'));
        }
        for ($y = 0; $y < $size - 1; $y++) for ($x = 0; $x < $size - 1; $x++) {
            if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) $score += 3;
        }
        $dark = 0;
        foreach ($m as $row) foreach ($row as $module) if ($module) $dark++;
        $total = $size * $size;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $score + 10 * max(0, $k);
    }
}
