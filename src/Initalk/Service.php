<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;

/** 이니톡 결제 도메인 객체 조립. 생성자에서 쓰기·외부 통신을 하지 않는다. */
final class Service
{
    public readonly Settings $settings;
    public readonly Events $events;
    public readonly Requests $requests;
    public readonly Notifier $notifier;
    public readonly Ledger $ledger;
    public readonly Checkout $checkout;
    public readonly CsvImport $import;

    public function __construct(public readonly App $app)
    {
        $this->settings = new Settings($app);
        $this->events = new Events($app->db());
        $this->requests = new Requests($app, $this->events);
        $this->notifier = new Notifier($app, $this->settings, $this->requests);
        $this->ledger = new Ledger($app->db());
        $this->checkout = new Checkout($app, $this->requests, $this->ledger);
        $this->import = new CsvImport($app, $this->requests, $this->notifier, $this->settings);
    }
}
