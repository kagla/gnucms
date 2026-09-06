<?php

declare(strict_types=1);

namespace GnuCms\Tests\View;

use GnuCms\Support\Clock;
use GnuCms\View\PhpView;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Interfaces\RouteParserInterface;

final class PhpViewTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gnucms-phpview-' . getmypid();
        @mkdir($this->dir . '/sub', 0777, true);
    }

    protected function tearDown(): void
    {
        // GLOB_BRACE 는 PHP 빌드에 따라 없다(이 환경에도 없다). 두 번 훑어 같은 일을 한다.
        $files = array_merge(glob($this->dir . '/*.php') ?: [], glob($this->dir . '/sub/*.php') ?: []);
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/sub');
        @rmdir($this->dir);
    }

    private function write(string $name, string $php): void
    {
        file_put_contents($this->dir . '/' . $name . '.php', $php);
    }

    private function view(): PhpView
    {
        $routes = $this->createMock(RouteParserInterface::class);
        $routes->method('urlFor')->willReturnCallback(
            static fn (string $name, array $p = [], array $q = []): string =>
                '/r/' . $name . ($p ? '/' . implode('/', $p) : '') . ($q ? '?' . http_build_query($q) : '')
        );
        return new PhpView(
            [$this->dir],
            $routes,
            '/base',
            static fn (string $path): string => '/themes/t/' . $path,
            static fn (string $html): string => '<div class="rich">' . $html . '</div>'
        );
    }

    public function testEscapesHtmlSpecialChars(): void
    {
        $this->write('a', '<?= $this->e($v) ?>|<?= $this->e(null) ?>');
        self::assertSame('&lt;b&gt;&quot;&#039;&amp;|', $this->view()->fetch('a', ['v' => '<b>"\'&']));
    }

    public function testChildBlocksFillLayoutAndOutsideOutputIsDropped(): void
    {
        $this->write('layout', "<html><title><?= \$this->block('title', '기본') ?></title><main><?= \$this->block('body') ?></main></html>");
        $this->write('page', "<?php \$this->layout('layout') ?>버림<?php \$this->start('body') ?>본문 <?= \$this->e(\$name) ?><?php \$this->stop() ?>");
        self::assertSame(
            '<html><title>기본</title><main>본문 홍길동</main></html>',
            $this->view()->fetch('page', ['name' => '홍길동'])
        );
    }

    public function testChildBlockBeatsParentDefaultAndNestedLayoutsWork(): void
    {
        // 루트 레이아웃은 start/stop 으로 '기본값을 정의하면서 출력' 한다 (정의하면서 출력).
        $this->write('layout', "[<?php \$this->start('chrome') ?>기본크롬<?php \$this->stop() ?>|<?= \$this->block('body') ?>]");
        // 중간 레이아웃은 자식이면서 부모다. 자기 블록은 조용히 잡히고, 자식 블록을 읽어 넣는다.
        $this->write('sub/layout', "<?php \$this->layout('layout') ?><?php \$this->start('chrome') ?>관리크롬(<?= \$this->block('body') ?>)<?php \$this->stop() ?>");
        $this->write('page', "<?php \$this->layout('sub/layout') ?><?php \$this->start('body') ?>글<?php \$this->stop() ?>");
        self::assertSame('[관리크롬(글)|글]', $this->view()->fetch('page'));
    }

    public function testHasSeesOnlyBlocksTheChildFilled(): void
    {
        $this->write('layout', "<?= \$this->has('search') ? 'S' : '-' ?>");
        $this->write('with', "<?php \$this->layout('layout') ?><?php \$this->start('search') ?>x<?php \$this->stop() ?>");
        $this->write('blank', "<?php \$this->layout('layout') ?><?php \$this->start('search') ?>  <?php \$this->stop() ?>");
        $this->write('none', "<?php \$this->layout('layout') ?>");
        self::assertSame('S', $this->view()->fetch('with'));
        self::assertSame('-', $this->view()->fetch('blank'), '공백만 있는 블록은 비어 있는 것이다');
        self::assertSame('-', $this->view()->fetch('none'));
    }

    public function testInsertPassesCurrentVariablesPlusExtras(): void
    {
        $this->write('part', '<?= $this->e($site) ?>/<?= $this->e($x) ?>');
        $this->write('page', "<?php \$this->insert('part', ['x' => 1]) ?>;<?php \$this->insert('part', ['x' => 2]) ?>");
        $view = $this->view();
        $view->addGlobal('site', 'S');
        self::assertSame('S/1;S/2', $view->fetch('page'));
    }

    public function testDataBeatsGlobalsWithSameName(): void
    {
        $this->write('a', '<?= $this->e($site) ?>');
        $view = $this->view();
        $view->addGlobal('site', 'global');
        self::assertSame('local', $view->fetch('a', ['site' => 'local']));
    }

    public function testExtensionInheritsSiteLayoutAssetsAndGlobalsWithoutChangingSiteView(): void
    {
        $this->write('layout', '<main><?= $this->block("body") ?></main>');
        $this->write('page', 'site');
        $this->write('sub/page', '<?php $this->layout("layout") ?><?php $this->start("body") ?><?= $this->e($site) ?>|<?= $this->asset("theme.css") ?>|<?= $this->url("admin.index") ?>|<?php $this->insert("part") ?><?php $this->stop() ?>');
        $this->write('sub/part', 'package');
        $view = $this->view();
        $view->addGlobal('site', '관리자');
        $extension = $view->forExtension('sample', $this->dir . '/sub');
        self::assertSame('<main>관리자|/themes/t/theme.css|/r/admin.index|package</main>', $extension->fetch('page'));
        self::assertSame('site', $view->fetch('page'));
        // 화면 전체를 복사하지 않아도 개별 조각을 테마에서 재정의할 수 있다.
        mkdir($this->dir . '/extensions/sample', 0700, true);
        try {
            file_put_contents($this->dir . '/extensions/sample/part.php', 'theme');
            self::assertStringContainsString('|theme</main>', $extension->fetch('page'));
            self::assertSame('package', $view->fetch('sub/part'));
        } finally {
            unlink($this->dir . '/extensions/sample/part.php');
            rmdir($this->dir . '/extensions/sample');
            rmdir($this->dir . '/extensions');
        }
    }

    public function testSharedExtensionLayoutUsesExistingCustomAdminLayout(): void
    {
        mkdir($this->dir . '/admin', 0700);
        $this->write('admin/layout', '<section>custom-admin|<?= $this->block("body") ?></section>');
        $this->write('sub/page', '<?php $this->layout("admin/extension") ?><?php $this->start("extension_body") ?>업무 화면<?php $this->stop() ?>');
        $view = $this->view()->forExtension('sample', $this->dir . '/sub');
        try {
            self::assertStringContainsString('custom-admin|', $view->fetch('page'));
            self::assertStringContainsString('업무 화면', $view->fetch('page'));
            $this->write('admin/extension', '<?php $this->layout("admin/layout") ?><?php $this->start("body") ?>theme-override|<?= $this->block("extension_body") ?><?php $this->stop() ?>');
            self::assertSame('<section>custom-admin|theme-override|업무 화면</section>', $view->fetch('page'));
        } finally {
            foreach (glob($this->dir . '/admin/*.php') as $file) unlink($file);
            rmdir($this->dir . '/admin');
        }
    }

    public function testUrlAssetHtmlJsonDateHelpers(): void
    {
        $this->write('a', "<?= \$this->url('posts.show', ['id' => '7'], ['q' => 'a b']) ?>|<?= \$this->asset('theme.css') ?>|<?= \$this->html('<p>x</p>') ?>|<?= \$this->json(['a' => '<']) ?>|<?= \$this->date('2026-08-30 01:02:03', 'Y.m.d H:i') ?>|<?= \$this->base ?>");
        self::assertSame(
            '/r/posts.show/7?q=a+b|/themes/t/theme.css|<div class="rich"><p>x</p></div>|{"a":"<"}|2026.08.30 01:02|/base',
            $this->view()->fetch('a')
        );
    }

    public function testCompactDateUsesTimeTodayAndMonthDayOtherwise(): void
    {
        Clock::freeze('2026-08-31 12:00:00');
        try {
            $this->write('a', "<?= \$this->compactDate('2026-08-31 09:07:00') ?>|<?= \$this->compactDate('2026-08-30 23:59:00') ?>");
            self::assertSame('09:07|08-30', $this->view()->fetch('a'));
        } finally {
            Clock::unfreeze();
        }
    }

    public function testDateHelpersConvertUtcToSiteTimezone(): void
    {
        Clock::freeze('2026-09-03 14:02:00');
        try {
            $this->write('a', "<?= \$this->date('2026-09-03 14:02:00', 'Y-m-d H:i') ?>|<?= \$this->compactDate('2026-09-03 14:02:00') ?>");
            $view = $this->view();
            $view->addGlobal('site', ['timezone' => 'Asia/Seoul']);
            self::assertSame('2026-09-03 23:02|23:02', $view->fetch('a'));
        } finally {
            Clock::unfreeze();
        }
    }

    public function testTruncateUsesCharacterCountForKoreanAndEnglish(): void
    {
        $this->write('a', "<?= \$this->e(\$this->truncate('abcdefghij', 8)) ?>|<?= \$this->e(\$this->truncate('가나다라마바사아자차', 8)) ?>");
        self::assertSame('abcdefgh…|가나다라마바사아…', $this->view()->fetch('a'));
    }

    public function testUrlAndAssetAreEscaped(): void
    {
        // 주소는 속성값 자리에 들어가므로 엔진이 이스케이프해 준다.
        $this->write('a', "<?= \$this->url('x', ['id' => 'a\"&b']) ?>|<?= \$this->asset('c\"&d.css') ?>");
        $out = $this->view()->fetch('a');
        self::assertSame('/r/x/a&quot;&amp;b|/themes/t/c&quot;&amp;d.css', $out);
    }

    public function testDefTreatsEmptyValuesAsMissing(): void
    {
        // def() 는 '비었으면' 기본값이다. ?? 는 null 만 본다 — "0"·0 에서 갈린다.
        $this->write('a', "<?= \$this->e(\$this->def('', 'd')) ?>|<?= \$this->e(\$this->def('0', 'd')) ?>"
            . "|<?= \$this->e(\$this->def(false, 'd')) ?>|<?= \$this->e(\$this->def(0, 'd')) ?>"
            . "|<?= \$this->e(\$this->def([], 'd')) ?>|<?= \$this->e(\$this->def(null, 'd')) ?>"
            . "|<?= \$this->e(\$this->def('v', 'd')) ?>");
        self::assertSame('d|0|d|0|d|d|v', $this->view()->fetch('a'));

        $t = new \GnuCms\View\PhpTemplate($this->view(), [], '/base');
        self::assertSame('d', $t->def('', 'd'));
        self::assertSame('0', $t->def('0', 'd'), '문자열 "0" 은 비어 있지 않다');
        self::assertSame('d', $t->def(false, 'd'));
        self::assertSame(0, $t->def(0, 'd'), '숫자 0 도 비어 있지 않다');
        self::assertSame('d', $t->def([], 'd'));
        self::assertSame('d', $t->def(null, 'd'));
    }

    public function testIconComesFromIconsFile(): void
    {
        $this->write('_icons', "<?php return ['home' => '<path d=\"M1 1\"/>'];");
        $this->write('a', "<?= \$this->icon('home', 18, 'x') ?>|<?= \$this->icon('nope') ?>");
        $out = $this->view()->fetch('a');
        self::assertStringContainsString('<svg class="icon x" width="18" height="18"', $out);
        self::assertStringContainsString('<path d="M1 1"/>', $out);
        self::assertStringContainsString('|<svg class="icon" width="20" height="20"', $out);
        self::assertStringContainsString('<circle cx="12" cy="12" r="8.6"/></svg>', $out, '모르는 이름은 빈 자리 대신 원을 낸다');
    }

    public function testInsertOnlyDropsOuterLocalsButKeepsGlobals(): void
    {
        $this->write('part', '<?= isset($x) ? $this->e($x) : \'-\' ?>/<?= $this->e($site) ?>');
        $this->write('page', "<?php \$this->insert('part', [], true) ?>;<?php \$this->insert('part', ['x' => 'inner'], true) ?>");
        $view = $this->view();
        $view->addGlobal('site', 'S');
        self::assertSame('-/S;inner/S', $view->fetch('page', ['x' => 'outer']));
    }

    public function testInsertWithoutOnlyStillPassesOuterLocals(): void
    {
        $this->write('part', '<?= isset($x) ? $this->e($x) : \'-\' ?>/<?= $this->e($site) ?>');
        $this->write('page', "<?php \$this->insert('part') ?>");
        $view = $this->view();
        $view->addGlobal('site', 'S');
        self::assertSame('outer/S', $view->fetch('page', ['x' => 'outer']));
    }

    public function testMissingTemplateThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->view()->fetch('does/not/exist');
    }

    public function testExceptionInsideTemplateDoesNotLeakBufferedOutput(): void
    {
        $this->write('a', "앞<?php throw new \\LogicException('x'); ?>");
        $level = ob_get_level();
        try {
            $this->view()->fetch('a');
            self::fail('예외가 나야 한다');
        } catch (\LogicException $e) {
            self::assertSame($level, ob_get_level(), '출력 버퍼가 남으면 다음 화면이 깨진다');
        }
    }

    public function testExceptionInsideOpenBlockDoesNotLeakBufferedOutput(): void
    {
        $this->write('a', "<?php \$this->start('x') ?>앞<?php throw new \\LogicException('x'); ?>");
        $level = ob_get_level();
        try {
            $this->view()->fetch('a');
            self::fail('예외가 나야 한다');
        } catch (\LogicException $e) {
            self::assertSame($level, ob_get_level(), '열린 블록 버퍼까지 걷어야 한다');
        }
    }

    public function testUnclosedStartThrowsInsteadOfSwallowingLaterOutput(): void
    {
        // stop() 을 빠뜨리면 capture() 자기 버퍼가 열린 채 남아 이후 출력이 전부 사라진다.
        $this->write('a', "<?php \$this->start('x') ?>본문");
        $level = ob_get_level();
        try {
            $this->view()->fetch('a');
            self::fail('예외가 나야 한다');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('stop() 없이 끝난 start()', $e->getMessage());
        }
        self::assertSame($level, ob_get_level(), '출력 버퍼가 남으면 다음 화면이 깨진다');
    }

    public function testLayoutCycleThrowsInsteadOfHanging(): void
    {
        $this->write('a', "<?php \$this->layout('b') ?>");
        $this->write('b', "<?php \$this->layout('a') ?>");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('레이아웃이 서로를 감쌉니다');
        $this->view()->fetch('a');
    }
}
