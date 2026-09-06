<?php

declare(strict_types=1);

namespace GnuCms\Install;

/**
 * 설치 1단계. 이 서버에서 GNUCMS 가 돌 수 있는지 항목별로 본다.
 * 확장 목록·PHP 판·아파치 모듈은 주입할 수 있어 테스트가 실제 서버에 매이지 않는다.
 */
final class ServerCheck
{
    public const MIN_PHP = '8.2.0';
    public const DRIVERS = ['pdo_sqlite', 'pdo_mysql'];

    private string $configDir;
    private string $storageDir;
    /** @var string[] */
    private array $extensions;
    private string $phpVersion;
    /** @var string[]|null null 이면 감지 불가(아파치 모듈 API 가 없는 환경) */
    private ?array $apacheModules;

    /**
     * @param string[]|null $extensions     실제 대신 쓸 확장 목록
     * @param string[]|null $apacheModules  실제 대신 쓸 아파치 모듈 목록. 생략하면 apache_get_modules() 가 있을 때만 읽는다
     */
    public function __construct(
        string $configDir,
        string $storageDir,
        ?array $extensions = null,
        ?string $phpVersion = null,
        ?array $apacheModules = null
    ) {
        $this->configDir = rtrim($configDir, '/');
        $this->storageDir = rtrim($storageDir, '/');
        $this->extensions = array_map('strtolower', $extensions ?? get_loaded_extensions());
        $this->phpVersion = $phpVersion ?? PHP_VERSION;
        $this->apacheModules = $apacheModules ?? (function_exists('apache_get_modules') ? apache_get_modules() : null);
    }

    /** @return array{ok: bool, items: list<array{label: string, ok: bool, required: bool, note: string}>} */
    public function run(): array
    {
        $items = [];
        $items[] = $this->item('PHP ' . self::MIN_PHP . ' 이상', version_compare($this->phpVersion, self::MIN_PHP, '>='), true, '지금 ' . $this->phpVersion);
        $items[] = $this->item('PDO 확장', $this->has('pdo'), true, 'DB 접속에 씁니다');

        $drivers = array_values(array_filter(self::DRIVERS, fn (string $d): bool => $this->has($d)));
        $items[] = $this->item(
            'PDO 드라이버 (sqlite·mysql 중 하나)',
            $drivers !== [],
            true,
            $drivers === [] ? '하나도 없습니다. 호스팅에 요청하세요' : '있음: ' . implode(', ', $drivers)
        );

        foreach ([
            'mbstring' => '한글 처리',
            'fileinfo' => '첨부 파일 종류 판별',
            'openssl'  => '메일 TLS 와 비밀값 암호화',
            'phar'     => 'zip 미지원 서버의 전체 백업 TAR 대체 생성과 검증',
        ] as $ext => $why) {
            $items[] = $this->item($ext . ' 확장', $this->has($ext), true, $why);
        }

        $items[] = $this->item('config/ 쓰기 가능', is_dir($this->configDir) && is_writable($this->configDir), true, $this->configDir);
        $items[] = $this->item('storage/ 쓰기 가능', is_dir($this->storageDir) && is_writable($this->storageDir), true, $this->storageDir);

        $items[] = $this->item('gd 확장', $this->has('gd'), false, '없으면 사진 축소본을 만들지 못합니다');
        $items[] = $this->item('zip 확장', $this->has('zip'), false, '있으면 PC에서 풀기 쉬운 ZIP 전체 백업을 생성합니다');

        $rewrite = $this->apacheModules === null ? null : in_array('mod_rewrite', $this->apacheModules, true);
        $items[] = $this->item(
            'mod_rewrite',
            $rewrite ?? true,
            false,
            $rewrite === null
                ? '감지할 수 없습니다. 없으면 주소가 /index.php/… 꼴이 됩니다'
                : ($rewrite ? '깔끔한 주소를 씁니다' : '없습니다. 주소가 /index.php/… 꼴이 됩니다')
        );

        $ok = true;
        foreach ($items as $item) {
            if ($item['required'] && !$item['ok']) {
                $ok = false;
            }
        }

        return ['ok' => $ok, 'items' => $items];
    }

    private function has(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions, true);
    }

    /** @return array{label: string, ok: bool, required: bool, note: string} */
    private function item(string $label, bool $ok, bool $required, string $note): array
    {
        return ['label' => $label, 'ok' => $ok, 'required' => $required, 'note' => $note];
    }
}
