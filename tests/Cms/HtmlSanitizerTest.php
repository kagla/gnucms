<?php

declare(strict_types=1);

namespace GnuCms\Tests\Cms;

use GnuCms\Cms\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testItKeepsEditorMarkupAndRemovesExecutableHtml(): void
    {
        $html = '<h2>안내</h2><p><strong>중요</strong>'
            . '<img src="/media/editor/2026/08/example.png" onerror="alert(1)"></p>'
            . '<script>alert(2)</script><a href="javascript:alert(3)">위험</a>';

        $clean = (new HtmlSanitizer())->clean($html);

        self::assertStringContainsString('<h2>안내</h2>', $clean);
        self::assertStringContainsString('<strong>중요</strong>', $clean);
        self::assertStringContainsString('src="/media/editor/2026/08/example.png"', $clean);
        self::assertStringNotContainsString('onerror', $clean);
        self::assertStringNotContainsString('<script', $clean);
        self::assertStringNotContainsString('javascript:', $clean);
    }

    public function testLegacyPlainTextKeepsLineBreaks(): void
    {
        $clean = (new HtmlSanitizer())->clean("첫 줄\n둘째 줄");

        self::assertSame("<p>첫 줄<br />\n둘째 줄</p>", $clean);
    }

    public function testTaglessEditorEntitiesAreNotEscapedAgain(): void
    {
        $sanitizer = new HtmlSanitizer();
        $clean = $sanitizer->clean('생활 &middot; 취향 &#183; 모아 &copy; 2026');
        self::assertSame('<p>생활 &middot; 취향 &#183; 모아 &copy; 2026</p>', $clean);
        self::assertSame('생활 · 취향 · 모아 © 2026', html_entity_decode(strip_tags($sanitizer->clean($clean)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testLiteralEntityExamplesKeepTheirMeaning(): void
    {
        $clean = (new HtmlSanitizer())->clean('문자 &amp;middot; 예시');
        self::assertSame('문자 &middot; 예시', html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testEncodedExecutableTagsStayText(): void
    {
        $sanitizer = new HtmlSanitizer();
        $clean = $sanitizer->clean('&lt;script&gt;alert(1)&lt;/script&gt;');
        self::assertSame('<script>alert(1)</script>', html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        self::assertStringNotContainsString('<script', $sanitizer->clean($clean));
    }
}
