<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

trait ExtensionFixtures
{
    private string $extensionRoot;

    private function createExtensionRoot(): void
    {
        $this->extensionRoot = sys_get_temp_dir() . '/gnucms-extensions-' . bin2hex(random_bytes(8));
        mkdir($this->extensionRoot, 0700, true);
    }

    private function package(string $key, array $manifest = [], ?string $bootstrap = null): void
    {
        [$section, $id] = explode('/', $key);
        $directory = $this->extensionRoot . '/' . $key;
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory . '/extension.json', json_encode(array_replace([
            'id' => $id, 'type' => $section === 'plugins' ? 'plugin' : 'module',
            'name' => $id, 'version' => '1.0.0', 'api' => 1,
        ], $manifest), JSON_THROW_ON_ERROR));
        file_put_contents($directory . '/bootstrap.php', $bootstrap ?? '<?php return static function ($context): void {};');
    }

    private function removeExtensionRoot(): void
    {
        if (!isset($this->extensionRoot)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->extensionRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->extensionRoot);
    }
}
