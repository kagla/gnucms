<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Extension\PackageSchema;

final class Schema
{
    public const KEY = 'modules/shop';
    public const VERSION = 2;
    public const TABLES = ['shop_settings', 'shop_products', 'shop_variants', 'shop_orders', 'shop_items',
        'shop_claims', 'shop_refunds', 'shop_money', 'shop_stock', 'shop_payouts', 'shop_events'];

    public static function install(PackageSchema $schema): void
    {
        $schema->install(self::KEY, self::VERSION, self::TABLES, static function ($db): void {
            $definitions = [
                'shop_settings' => 'id VARCHAR(32) PRIMARY KEY, payload {TEXT} NOT NULL',
                'shop_products' => 'id VARCHAR(32) PRIMARY KEY, name VARCHAR(150) NOT NULL, description {TEXT} NOT NULL,
                    image VARCHAR(100) NOT NULL, option1_name VARCHAR(60) NOT NULL, option2_name VARCHAR(60) NOT NULL,
                    active SMALLINT NOT NULL, version INTEGER NOT NULL, created_at BIGINT NOT NULL',
                'shop_variants' => 'id VARCHAR(32) PRIMARY KEY, product_id VARCHAR(32) NOT NULL, option1 VARCHAR(60){OPTION_COLLATION} NOT NULL,
                    option2 VARCHAR(60){OPTION_COLLATION} NOT NULL, price BIGINT NOT NULL CHECK (price > 0), stock INTEGER NOT NULL CHECK (stock >= 0),
                    active SMALLINT NOT NULL, version INTEGER NOT NULL, UNIQUE (product_id, option1, option2)',
                'shop_orders' => 'id VARCHAR(32) PRIMARY KEY, user_id VARCHAR(100) NOT NULL, checkout_key VARCHAR(64) NOT NULL UNIQUE,
                    order_name VARCHAR(200) NOT NULL, customer {TEXT} NOT NULL, provider VARCHAR(10) NOT NULL,
                    environment VARCHAR(4) NOT NULL, config_revision VARCHAR(32) NOT NULL, status VARCHAR(24) NOT NULL,
                    total BIGINT NOT NULL CHECK (total > 0), shipping BIGINT NOT NULL, refunded BIGINT NOT NULL,
                    shipping_refunded BIGINT NOT NULL, transaction_id VARCHAR(200) UNIQUE, checkout_started BIGINT NOT NULL,
                    paid_at BIGINT NOT NULL, created_at BIGINT NOT NULL, expires_at BIGINT NOT NULL,
                    stock_released SMALLINT NOT NULL, carrier VARCHAR(80) NOT NULL, tracking VARCHAR(80) NOT NULL,
                    shipped_at BIGINT NOT NULL, delivered_at BIGINT NOT NULL, version INTEGER NOT NULL, needs_review SMALLINT NOT NULL,
                    late_cancel_at BIGINT NOT NULL DEFAULT 0, checked_at BIGINT NOT NULL DEFAULT 0',
                'shop_items' => 'id VARCHAR(32) PRIMARY KEY, order_id VARCHAR(32) NOT NULL, variant_id VARCHAR(32) NOT NULL,
                    product_id VARCHAR(32) NOT NULL, name VARCHAR(150) NOT NULL, options VARCHAR(250) NOT NULL,
                    price BIGINT NOT NULL, quantity INTEGER NOT NULL CHECK (quantity > 0), returned INTEGER NOT NULL,
                    exchanged INTEGER NOT NULL, exchange_claim_id VARCHAR(32) NOT NULL DEFAULT \'\'',
                'shop_claims' => 'id VARCHAR(32) PRIMARY KEY, order_id VARCHAR(32) NOT NULL, item_id VARCHAR(32) NOT NULL,
                    request_key VARCHAR(64) NOT NULL UNIQUE, kind VARCHAR(10) NOT NULL, quantity INTEGER NOT NULL,
                    reason VARCHAR(1000) NOT NULL, status VARCHAR(24) NOT NULL, replacement_id VARCHAR(32) NOT NULL,
                    restock SMALLINT NOT NULL, carrier VARCHAR(80) NOT NULL, tracking VARCHAR(80) NOT NULL,
                    note VARCHAR(1000) NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL',
                'shop_refunds' => 'id VARCHAR(32) PRIMARY KEY, order_id VARCHAR(32) NOT NULL, claim_id VARCHAR(32) NOT NULL,
                    request_key VARCHAR(64) NOT NULL UNIQUE, amount BIGINT NOT NULL CHECK (amount > 0), remaining BIGINT NOT NULL,
                    shipping_amount BIGINT NOT NULL, deduction BIGINT NOT NULL DEFAULT 0, reason VARCHAR(1000) NOT NULL, status VARCHAR(16) NOT NULL,
                    provider_ref VARCHAR(100), created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL',
                'shop_money' => 'id VARCHAR(64) PRIMARY KEY, order_id VARCHAR(32) NOT NULL, environment VARCHAR(4) NOT NULL,
                    provider VARCHAR(10) NOT NULL, kind VARCHAR(16) NOT NULL, amount BIGINT NOT NULL, occurred_at BIGINT NOT NULL,
                    reference VARCHAR(200) NOT NULL',
                'shop_stock' => 'id VARCHAR(32) PRIMARY KEY, variant_id VARCHAR(32) NOT NULL, delta INTEGER NOT NULL,
                    kind VARCHAR(20) NOT NULL, reference VARCHAR(100) NOT NULL, created_at BIGINT NOT NULL',
                'shop_payouts' => 'id VARCHAR(32) PRIMARY KEY, request_key VARCHAR(64) NOT NULL UNIQUE, environment VARCHAR(4) NOT NULL,
                    provider VARCHAR(10) NOT NULL, batch_ref VARCHAR(100) NOT NULL, sales_from VARCHAR(10) NOT NULL, sales_to VARCHAR(10) NOT NULL,
                    deposit_date VARCHAR(10) NOT NULL, gross BIGINT NOT NULL, refunds BIGINT NOT NULL, fees BIGINT NOT NULL,
                    adjustment BIGINT NOT NULL, deposited BIGINT NOT NULL, note VARCHAR(1000) NOT NULL, created_at BIGINT NOT NULL, version INTEGER NOT NULL,
                    UNIQUE (environment, provider, batch_ref)',
                'shop_events' => 'id VARCHAR(32) PRIMARY KEY, order_id VARCHAR(32) NOT NULL, actor VARCHAR(100) NOT NULL,
                    kind VARCHAR(40) NOT NULL, note VARCHAR(1000) NOT NULL, created_at BIGINT NOT NULL',
            ];
            $types = $db->dialect()->typeMap() + ['{OPTION_COLLATION}' => $db->dialect()->name() === 'mysql' ? ' COLLATE utf8mb4_bin' : ''];
            foreach ($definitions as $table => $definition) {
                $db->execute('CREATE TABLE IF NOT EXISTS ' . $db->table($table) . ' (' . strtr($definition, $types) . ')' . $db->dialect()->tableSuffix());
            }
            // SQLite VARCHAR에는 길이 제한이 없다. MySQL의 기존 두 컬럼만 멱등 확장한다.
            if ($db->dialect()->name() === 'mysql') {
                foreach (['shop_orders' => ['transaction_id', 'NULL'], 'shop_money' => ['reference', 'NOT NULL']] as $table => [$column, $nullable]) {
                    $info = $db->selectOne('SELECT CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$db->tableName($table), $column]);
                    if ((int) $info['max_length'] < 200) $db->execute('ALTER TABLE ' . $db->table($table) . ' MODIFY ' . $db->q($column) . ' VARCHAR(200) ' . $nullable);
                }
            }
            foreach (['shop_order_user' => ['shop_orders', 'user_id'], 'shop_item_order' => ['shop_items', 'order_id'],
                'shop_claim_order' => ['shop_claims', 'order_id'], 'shop_refund_order' => ['shop_refunds', 'order_id'],
                'shop_money_date' => ['shop_money', 'occurred_at'], 'shop_stock_variant' => ['shop_stock', 'variant_id'],
                'shop_event_order' => ['shop_events', 'order_id']] as $index => [$table, $column]) {
                $physical = $db->prefix() . $index;
                $exists = match ($db->dialect()->name()) {
                    'sqlite' => $db->selectOne("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$physical]),
                    'mysql' => $db->selectOne('SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$db->tableName($table), $physical]),
                };
                if ($exists === null) $db->execute('CREATE INDEX ' . $db->index($index) . ' ON ' . $db->table($table) . ' (' . $db->q($column) . ')');
            }
        });
    }
}
