<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCmsDemo\Modules\Reservation\ReservationPreview;
use GnuCmsDemo\Modules\Reservation\PreviewController;

require_once __DIR__ . '/src/ReservationPreview.php';
require_once __DIR__ . '/src/PreviewController.php';

return static function (Context $context): void {
    // 다른 패키지의 PHP 파일을 require 하지 않는다. 서비스가 없으면 독립적으로 실행한다.
    $format = $context->service('plugins/demo-message', 'format');
    $service = new ReservationPreview($format instanceof Closure ? $format : null);
    $controller = new PreviewController($service);
    $context->route('GET', '/preview', [$controller, 'show'], admin: true);
    $context->route('POST', '/preview', [$controller, 'preview'], admin: true);
};
