<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCmsDemo\Plugins\Message\MessageFormatter;
use GnuCmsDemo\Plugins\Message\PreviewController;

// 코어 Composer 설정을 수정하지 않고 패키지 안의 클래스를 불러온다.
require_once __DIR__ . '/src/MessageFormatter.php';
require_once __DIR__ . '/src/PreviewController.php';

return static function (Context $context): void {
    $formatter = new MessageFormatter();
    // 공개 계약: Closure(string $title, string $body): string. 소비자는 이 패키지 클래스를 몰라도 된다.
    $context->provide('format', $formatter->format(...));
    $controller = new PreviewController($formatter);
    $context->route('GET', '/preview', [$controller, 'show'], admin: true);
    $context->route('POST', '/preview', [$controller, 'preview'], admin: true);
};
