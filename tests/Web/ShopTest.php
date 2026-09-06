<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Modules\Shop\Service;
use GnuCms\Modules\Shop\Store;
use GnuCms\Payment\Settings;
use GnuCms\Tests\Shop\FakeGateway;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';

final class ShopTest extends WebTestCase
{
    private App $app;
    private Service $shop;
    private string $root;

    private function setupShop(array $config, bool $install = true, string $base = ''): void
    {
        session_name(GNUCMS_ID . '_session');
        session_start(); $_SESSION = []; session_write_close();
        $this->root = sys_get_temp_dir() . '/gnucms-shop-web-' . Store::id();
        $config['prefix'] = 'sw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'uploads' => ['dir' => $this->root . '/uploads'], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test' . $base]]);
        $manager = new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions'));
        $manager->setEnabledMany(['modules/shop' => true, 'plugins/payment-inicis' => true, 'plugins/payment-kcp' => true, 'plugins/payment-kspay' => true, 'plugins/payment-toss' => true]);
        $this->shop = new Service($this->app, ['inicis' => new FakeGateway()]);
        if ($install) $this->shop->install();
    }

    private function signIn(bool $admin): string
    {
        $id = $this->app->users()->create(bin2hex(random_bytes(4)) . '@example.test', '', ($admin ? '운영자' : '구매자') . bin2hex(random_bytes(3)), $admin);
        $this->get($this->app, '/login');
        session_start(); $_SESSION['user_id'] = $id; $_SESSION['session_epoch'] = 0; session_write_close();
        return (string) $id;
    }

    private function csrf(array $body): array { return $body + ['csrf_token' => $_SESSION['csrf_token']]; }

    private function product(): array
    {
        $id = $this->shop->catalog->save(['name' => '<script>상품</script>', 'description' => '상품 설명', 'price' => 10000, 'stock' => 10, 'active' => '1']);
        return $this->shop->catalog->product($id);
    }

    private function openShop(): void
    {
        $this->shop->saveSettings(['name' => '상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'live', 'open' => '1']);
    }

    #[DataProvider('connectionProvider')]
    public function testAdminProductListFormAndVariantSubmissionAreSeparateAndGuarded(array $config): void
    {
        $this->setupShop($config);
        $paths = ['/admin/shop', '/admin/shop/products', '/admin/shop/products/new', '/admin/shop/products/edit'];
        foreach ($paths as $path) self::assertSame(401, $this->get($this->app, $path)->getStatusCode());
        $this->signIn(false);
        foreach ($paths as $path) self::assertSame(403, $this->get($this->app, $path)->getStatusCode());
        $this->signIn(true);
        $list = $this->body($this->get($this->app, '/admin/shop/products'));
        self::assertStringContainsString('href="/admin/shop/products/new"', $list);
        self::assertStringNotContainsString('name="name"', $list);
        $form = $this->body($this->get($this->app, '/admin/shop/products/new'));
        self::assertStringContainsString('name="variants[0][price]"', $form);
        self::assertStringContainsString('name="variants[0][stock]"', $form);
        self::assertStringNotContainsString('name="q"', $form);
        $input = ['action' => 'build', 'name' => '<b>옵션 상품</b>', 'active' => '1', 'option1_name' => '색상', 'option1_values' => '검정,흰색', 'price' => '11000', 'stock' => '4'];
        self::assertSame(403, $this->post($this->app, '/admin/shop/products/new', $input)->getStatusCode());
        $preview = $this->post($this->app, '/admin/shop/products/new', $this->csrf($input));
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('name="variants[1][stock]"', $this->body($preview));
        self::assertSame([], $this->shop->catalog->listing(true));
        $input['action'] = 'save';
        $input['variants'] = [['option1' => '검정', 'option2' => '', 'price' => '11000', 'stock' => '4'], ['option1' => '흰색', 'option2' => '', 'price' => '17000', 'stock' => '0']];
        $bad = $input; $bad['variants'][1]['stock'] = '-1';
        $response = $this->post($this->app, '/admin/shop/products/new', $this->csrf($bad));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('&lt;b&gt;옵션 상품&lt;/b&gt;', $this->body($response));
        self::assertStringContainsString('value="17000"', $this->body($response));
        self::assertSame([], $this->shop->catalog->listing(true));
        $response = $this->post($this->app, '/admin/shop/products/new', $this->csrf($input));
        self::assertSame(303, $response->getStatusCode(), substr($this->body($response), 0, 200));
        self::assertStringStartsWith('/admin/shop/products/edit?id=', $response->getHeaderLine('Location'));
        $id = $this->shop->catalog->listing(true)[0]['id'];
        $body = $this->body($this->get($this->app, $response->getHeaderLine('Location')));
        self::assertStringContainsString('상품을 저장했습니다', $body);
        self::assertStringNotContainsString('name="q"', $body);
        $body = $this->body($this->get($this->app, '/shop/product', ['id' => $id]));
        self::assertStringContainsString('11,000원', $body);
        self::assertStringContainsString('17,000원 · 품절', $body);
        foreach (['/shop', '/modules/shop'] as $prefix) {
            self::assertSame('/admin/shop/products', $this->get($this->app, $prefix . '/products')->getHeaderLine('Location'));
            self::assertSame('/admin/shop/products/edit?id=' . $id, $this->get($this->app, $prefix . '/products', ['id' => $id])->getHeaderLine('Location'));
            self::assertSame(403, $this->post($this->app, $prefix . '/products', $input)->getStatusCode());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testCostFormAndProfitReportAreAdminOnlyAndKeepInvalidEdits(array $config): void
    {
        $this->setupShop($config); $this->openShop(); $user = $this->signIn(true);
        $input = ['action' => 'save', 'name' => '원가 확인 상품', 'active' => '1', 'price' => '10000', 'cost_price' => '4321', 'stock' => '5'];
        $created = $this->post($this->app, '/admin/shop/products/new', $this->csrf($input));
        self::assertSame(303, $created->getStatusCode());
        $product = $this->shop->catalog->listing(true)[0]; $p = $this->shop->catalog->product($product['id'], true);
        self::assertSame(4321, (int) $p['variants'][0]['cost_price']);
        $form = $this->body($this->get($this->app, $created->getHeaderLine('Location')));
        self::assertStringContainsString('name="variants[0][cost_price]"', $form);
        self::assertStringContainsString('value="4321"', $form);
        $bad = array_replace($input, ['id' => $p['id'], 'version' => $p['version'], 'name' => '입력 중인 상품', 'cost_price' => '-1']);
        $response = $this->post($this->app, '/admin/shop/products/edit', $this->csrf($bad));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('value="입력 중인 상품"', $this->body($response));
        self::assertSame($p, $this->shop->catalog->product($p['id'], true));
        $cart = [$p['variants'][0]['id'] => 2];
        $order = $this->shop->createOrder($user, $cart, ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test',
            'postcode' => '00000', 'address' => '테스트 주소', 'consent' => '1'], 'inicis', Store::id(), $this->shop->quote($cart)['total']);
        $this->shop->gateways['inicis']->paid($order); $this->shop->sync($order['id']);
        $date = (new \DateTimeImmutable('@' . \GnuCms\Support\Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d');
        $report = $this->body($this->get($this->app, '/admin/shop/settlement', ['from' => $date, 'to' => $date]));
        self::assertStringContainsString('상품별 원가와 이익', $report);
        self::assertStringContainsString('8,642', $report); self::assertStringContainsString('11,358', $report);
        $public = $this->body($this->get($this->app, '/shop/product', ['id' => $p['id']]));
        self::assertStringNotContainsString('cost_price', $public); self::assertStringNotContainsString('4,321', $public);
        $orderPage = $this->body($this->get($this->app, '/shop/order', ['id' => $order['id']]));
        self::assertStringNotContainsString('cost_price', $orderPage); self::assertStringNotContainsString('4,321', $orderPage);
        $this->signIn(false);
        self::assertSame(403, $this->get($this->app, '/admin/shop/settlement')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testProductListShowsOptionNamesAndEscapedActiveValues(array $config): void
    {
        $this->setupShop($config); $this->signIn(true);
        $input = ['name' => '옵션 목록 상품', 'active' => '1', 'price' => 10000, 'stock' => 0,
            'option1_name' => '색상 "선택"', 'option1_values' => '<검정>,흰색,제거할 색상',
            'option2_name' => '사이즈', 'option2_values' => 'S,M'];
        $id = $this->shop->catalog->save($input);
        $product = $this->shop->catalog->product($id);
        $this->shop->catalog->save(array_replace($input, ['id' => $id, 'version' => $product['version'], 'option1_values' => '<검정>,흰색']));
        $plainId = $this->shop->catalog->save(['name' => '옵션 없는 상품']);
        $singleId = $this->shop->catalog->save(['name' => '단일 옵션 상품', 'option1_name' => '용량', 'option1_values' => '100ml,200ml']);
        $listing = array_column($this->shop->catalog->listing(true), null, 'id');
        self::assertSame(['색상 "선택"', '사이즈'], array_column($listing[$id]['options'], 'name'));
        self::assertEqualsCanonicalizing(['<검정>', '흰색'], $listing[$id]['options'][0]['values']);
        self::assertEqualsCanonicalizing(['S', 'M'], $listing[$id]['options'][1]['values']);
        self::assertSame([], $listing[$plainId]['options']);
        self::assertCount(1, $listing[$singleId]['options']);
        self::assertEqualsCanonicalizing(['100ml', '200ml'], $listing[$singleId]['options'][0]['values']);
        $body = $this->body($this->get($this->app, '/admin/shop/products'));
        self::assertStringContainsString('<th>옵션</th>', $body);
        self::assertSame(3, substr_count($body, 'data-label="옵션"'));
        foreach ($listing[$id]['options'] as $option) {
            $title = htmlspecialchars($option['name'] . ': ' . implode(', ', $option['values']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            self::assertStringContainsString('title="' . $title . '"', $body);
            self::assertStringContainsString('aria-label="' . $title . '"', $body);
        }
        self::assertStringContainsString('>없음</span>', $body);
        self::assertStringNotContainsString('<검정>', $body);
        self::assertStringNotContainsString('제거할 색상', $body);
        $filtered = $this->body($this->get($this->app, '/admin/shop/products', ['q' => '단일 옵션']));
        self::assertStringContainsString('title="용량: 100ml, 200ml"', $filtered);
        self::assertStringNotContainsString('title="사이즈:', $filtered);
        self::assertSame([], $this->shop->catalog->listing(true, query: '일치하지 않는 상품명'));
    }

    #[DataProvider('connectionProvider')]
    public function testDescriptionEditorSavesFormattingAndImagesAcrossValidationErrors(array $config): void
    {
        $this->setupShop($config); $this->signIn(true);
        $form = $this->body($this->get($this->app, '/admin/shop/products/new'));
        self::assertStringContainsString('/vendor/ckeditor4/ckeditor.js', $form);
        self::assertStringContainsString('data-cms-editor', $form);
        self::assertSame(1, preg_match('/name="image_key" value="([a-f0-9]{32})"/', $form, $matches));
        $key = $matches[1];
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=');
        $upload = function (string $imageKey) use ($png): array {
            $source = $this->root . '/description.png'; file_put_contents($source, $png);
            $response = $this->upload($this->app, '/admin/editor/images?' . http_build_query(['csrf_token' => $_SESSION['csrf_token'], 'image_key' => $imageKey]),
                ['upload' => new \Slim\Psr7\UploadedFile($source, 'description.png', 'image/png', strlen($png))]);
            self::assertSame(200, $response->getStatusCode());
            return json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);
        };
        $used = $upload($key); $unused = $upload($key);
        $other = $upload(Store::id());
        $description = '<h2>소재 안내</h2><p><strong>면 소재</strong></p><ul><li>찬물 세탁</li></ul>'
            . '<img src="' . $used['url'] . '" onerror="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">링크</a>';
        $input = ['action' => 'build', 'name' => '서식 상품', 'active' => '1', 'price' => '12000', 'stock' => '3',
            'description' => $description, 'image_key' => $key, 'uploaded_images' => $used['fileName'] . ',' . $unused['fileName']];
        $preview = $this->post($this->app, '/admin/shop/products/new', $this->csrf($input));
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('name="image_key" value="' . $key . '"', $this->body($preview));
        self::assertSame([], $this->shop->catalog->listing(true));
        $bad = array_replace($input, ['action' => 'save', 'price' => '-1']);
        self::assertSame(422, $this->post($this->app, '/admin/shop/products/new', $this->csrf($bad))->getStatusCode());
        foreach ([$used, $unused, $other] as $image) self::assertSame(200, $this->get($this->app, $image['url'])->getStatusCode());
        $input['action'] = 'save';
        $saved = $this->post($this->app, '/admin/shop/products/new', $this->csrf($input));
        self::assertSame(303, $saved->getStatusCode());
        $product = $this->shop->catalog->listing(true)[0];
        self::assertStringContainsString('<strong>면 소재</strong>', $product['description']);
        foreach (['<script', 'onerror', 'javascript:'] as $badHtml) self::assertStringNotContainsString($badHtml, $product['description']);
        self::assertSame(200, $this->get($this->app, $used['url'])->getStatusCode());
        self::assertSame(404, $this->get($this->app, $unused['url'])->getStatusCode());
        self::assertSame(200, $this->get($this->app, $other['url'])->getStatusCode());
        $edit = $this->body($this->get($this->app, $saved->getHeaderLine('Location')));
        self::assertStringContainsString('&lt;strong&gt;면 소재&lt;/strong&gt;', $edit);
        self::assertStringNotContainsString('name="image_key" value="' . $key . '"', $edit);
        $this->signIn(false);
        $public = $this->body($this->get($this->app, '/shop/product', ['id' => $product['id']]));
        self::assertStringContainsString('<strong>면 소재</strong>', $public);
        self::assertStringContainsString('<ul><li>찬물 세탁</li></ul>', $public);
        self::assertStringContainsString('href="' . $used['url'] . '"', $public);
        self::assertStringNotContainsString('onerror=', $public);
    }

    #[DataProvider('connectionProvider')]
    public function testLegacyDescriptionLineBreaksAndEmptyDescriptionsRemainEditable(array $config): void
    {
        $this->setupShop($config); $product = $this->product(); $this->signIn(true);
        $legacy = "첫 번째 줄\n두 번째 줄 & 설명";
        $this->shop->store->update('shop_products', $product['id'], ['description' => $legacy]);
        $form = $this->body($this->get($this->app, '/admin/shop/products/edit', ['id' => $product['id']]));
        self::assertStringContainsString('첫 번째 줄&lt;br /&gt;', $form);
        self::assertSame($legacy, $this->shop->catalog->product($product['id'])['description'], 'opening editor must not change stored data');
        $public = $this->body($this->get($this->app, '/shop/product', ['id' => $product['id']]));
        self::assertStringContainsString("첫 번째 줄<br />\n두 번째 줄 &amp; 설명", $public);
        $input = ['action' => 'save', 'id' => $product['id'], 'version' => $product['version'], 'name' => '빈 설명 상품', 'active' => '1', 'description' => ''];
        self::assertSame(303, $this->post($this->app, '/admin/shop/products/edit', $this->csrf($input))->getStatusCode());
        self::assertSame('', $this->shop->catalog->product($product['id'])['description']);
    }

    #[DataProvider('connectionProvider')]
    public function testLegacyShopCanOpenTheGalleryUpgradeFromTheUnifiedForm(array $config): void
    {
        $this->setupShop($config); $this->signIn(true);
        $db = $this->app->db();
        $db->execute('DROP TABLE ' . $db->table('shop_product_images'));
        $db->update('extension_schemas', ['schema_version' => 2], 'package_key = :key', ['key' => \GnuCms\Modules\Shop\Schema::KEY]);
        self::assertFalse($this->shop->images->ready());
        $body = $this->body($this->get($this->app, '/admin/shop/products/new'));
        self::assertStringContainsString('href="/admin/shop">쇼핑몰 데이터 관리', $body);
        self::assertStringNotContainsString('name="images[]"', $body);
        $body = $this->body($this->get($this->app, '/admin/shop'));
        self::assertStringContainsString('name="action" value="install">쇼핑몰 데이터 설치/갱신', $body);
        self::assertSame(303, $this->post($this->app, '/admin/shop', $this->csrf(['action' => 'install']))->getStatusCode());
        self::assertTrue($this->shop->images->ready());
        self::assertStringContainsString('name="images[]"', $this->body($this->get($this->app, '/admin/shop/products/new')));
    }

    #[DataProvider('connectionProvider')]
    public function testRegistrationAndEditSubmitGalleryInTheProductForm(array $config): void
    {
        $this->setupShop($config); $this->signIn(true);
        $body = $this->body($this->get($this->app, '/admin/shop/products/new'));
        $dom = new \DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $body);
        $xpath = new \DOMXPath($dom);
        self::assertSame(1, $xpath->query('//form[@id="shop-product-form"]//input[@name="images[]"]')->length);
        self::assertSame(1, $xpath->query('//form[@id="shop-product-form" and @enctype="multipart/form-data"]')->length);
        $source = $this->root . '/combined.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=');
        file_put_contents($source, $png);
        $upload = static fn ($error = UPLOAD_ERR_OK) => new \Slim\Psr7\UploadedFile($source, 'photo.png', 'image/png', strlen($png), $error);
        $input = ['action' => 'save', 'name' => '사진과 함께 등록', 'active' => '1', 'gallery_present' => '1', 'price' => '23000', 'stock' => '4'];
        // 자바스크립트가 없어 새 파일 순서 필드가 없어도 선택 순서대로 저장한다.
        $saved = $this->postWithFiles($this->app, '/admin/shop/products/new', $this->csrf($input), ['images' => [$upload(), $upload()]]);
        self::assertSame(303, $saved->getStatusCode());
        $product = $this->shop->catalog->product($this->shop->catalog->listing(true)[0]['id']);
        self::assertCount(2, $product['images']);
        self::assertSame(23000, (int) $product['variants'][0]['price']);
        $input = array_replace($input, ['id' => $product['id'], 'version' => $product['version'], 'name' => '사진 순서와 함께 수정',
            'image_order' => array_reverse(array_column($product['images'], 'id'))]);
        $path = '/admin/shop/products/edit';
        $failed = $this->postWithFiles($this->app, $path, $this->csrf($input), ['images' => [$upload(UPLOAD_ERR_PARTIAL)]]);
        self::assertSame(422, $failed->getStatusCode());
        self::assertStringContainsString('이미지 파일을 다시 선택', $this->body($failed));
        self::assertStringContainsString('value="사진 순서와 함께 수정"', $this->body($failed));
        self::assertSame($product, $this->shop->catalog->product($product['id']));
        $saved = $this->postWithFiles($this->app, $path, $this->csrf($input), ['images' => [$upload(UPLOAD_ERR_NO_FILE)]]);
        self::assertSame(303, $saved->getStatusCode());
        $fresh = $this->shop->catalog->product($product['id']);
        self::assertSame('사진 순서와 함께 수정', $fresh['name']);
        self::assertSame($input['image_order'], array_column($fresh['images'], 'id'));
        self::assertSame($product['images'][1]['filename'], $fresh['image']);
        self::assertCount(2, glob($this->root . '/uploads/shop/*'));
    }

    #[DataProvider('connectionProvider')]
    public function testImageUploadAndOrderPersistWithCsrfOwnershipAndPublicGallery(array $config): void
    {
        $this->setupShop($config); $product = $this->product();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=');
        $source = $this->root . '/source.png'; file_put_contents($source, $png);
        $upload = static fn () => new \Slim\Psr7\UploadedFile($source, 'photo.png', 'image/png', strlen($png));
        $path = '/admin/shop/products/edit';
        $input = ['id' => $product['id'], 'version' => $product['version'], 'action' => 'image'];
        self::assertSame(401, $this->postWithFiles($this->app, $path, $input, ['images' => [$upload()]])->getStatusCode());
        $this->signIn(false);
        self::assertSame(403, $this->postWithFiles($this->app, $path, $this->csrf($input), ['images' => [$upload()]])->getStatusCode());
        $this->signIn(true);
        self::assertSame(403, $this->postWithFiles($this->app, $path, $input, ['images' => [$upload()]])->getStatusCode());
        $response = $this->postWithFiles($this->app, $path, $this->csrf($input), ['images' => [$upload(), $upload(), $upload()]]);
        self::assertSame(303, $response->getStatusCode());
        $fresh = $this->shop->catalog->product($product['id']);
        self::assertCount(3, $fresh['images']);
        $body = $this->body($this->get($this->app, $path, ['id' => $product['id']]));
        self::assertStringContainsString('name="images[]"', $body);
        self::assertStringContainsString(' multiple', $body);
        self::assertStringContainsString('name="image_order[]"', $body);
        self::assertStringNotContainsString('id="shop-image-order"', $body);
        $ids = array_reverse(array_column($fresh['images'], 'id'));
        $order = ['id' => $product['id'], 'version' => $fresh['version'], 'action' => 'image-order', 'image_ids' => $ids];
        self::assertSame(403, $this->post($this->app, $path, $order)->getStatusCode());
        self::assertSame(303, $this->post($this->app, $path, $this->csrf($order))->getStatusCode());
        self::assertSame($ids, array_column($this->shop->catalog->product($product['id'])['images'], 'id'));
        self::assertSame(422, $this->post($this->app, $path, $this->csrf($order))->getStatusCode());
        $this->signIn(false);
        $body = $this->body($this->get($this->app, '/shop/product', ['id' => $product['id']]));
        self::assertSame(3, substr_count($body, 'data-product-thumb'));
        self::assertStringContainsString('id="shop-product-image"', $body);
        self::assertStringNotContainsString('image_ids[]', $body);
        self::assertLessThan(strpos($body, $fresh['images'][0]['filename']), strpos($body, $fresh['images'][2]['filename']));
        self::assertSame(403, $this->post($this->app, $path, $this->csrf($order))->getStatusCode());
        $response = $this->get($this->app, '/shop/image', ['file' => $fresh['images'][0]['filename']]);
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame($png, $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testShortAndLegacyShopRoutesPreserveCsrfAdminAndLoginGuards(array $config): void
    {
        $this->setupShop($config); $product = $this->product();
        foreach (['/shop', '/shop/', '/modules/shop/'] as $path) {
            $body = $this->body($this->get($this->app, $path));
            self::assertStringContainsString('href="/shop/product?id=' . $product['id'], $body);
            self::assertStringNotContainsString('/modules/shop/', $body);
        }
        foreach (['/shop', '/modules/shop'] as $prefix) {
            self::assertSame(401, $this->get($this->app, $prefix . '/admin')->getStatusCode());
            self::assertSame(403, $this->post($this->app, $prefix . '/cart', ['action' => 'clear'])->getStatusCode());
        }
        self::assertSame('/login', $this->get($this->app, '/modules/shop/checkout')->getHeaderLine('Location'));
        self::assertSame('/shop/checkout', $_SESSION['login_destination']['path']);
        $this->signIn(false);
        foreach (['/shop', '/modules/shop'] as $prefix) self::assertSame(403, $this->get($this->app, $prefix . '/admin')->getStatusCode());
        $response = $this->post($this->app, '/modules/shop/cart', $this->csrf(['action' => 'add', 'variant_id' => $product['variants'][0]['id'], 'quantity' => '1']));
        self::assertSame('/shop/cart', $response->getHeaderLine('Location'));
        self::assertSame([$product['variants'][0]['id'] => 1], $_SESSION['shop_cart']);
        $this->signIn(true);
        $body = $this->body($this->get($this->app, '/admin/modules'));
        self::assertStringContainsString('href="/admin/shop"', $body);
        self::assertStringContainsString('href="/shop"', $body);
        self::assertSame('/admin/shop', $this->get($this->app, '/modules/shop/admin')->getHeaderLine('Location'));
    }

    #[DataProvider('connectionProvider')]
    public function testCustomerCanRequestReturnAndExchangeFromDeliveredOrder(array $config): void
    {
        $this->setupShop($config); $this->openShop(); $user = $this->signIn(false);
        $id = $this->shop->catalog->save(['name' => '옵션 상품', 'description' => '상품 설명', 'price' => 10000, 'stock' => 10, 'active' => '1', 'option1_name' => '색상', 'option1_values' => '흰색,검정']);
        $product = $this->shop->catalog->product($id);
        $order = $this->shop->createOrder($user, [$product['variants'][0]['id'] => 2],
            ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'consent' => '1'], 'inicis', Store::id(), 23000);
        $this->shop->gateways['inicis']->paid($order); $this->shop->sync($order['id']);
        $this->shop->fulfill($order['id'], 'ship', ['carrier' => '택배', 'tracking' => '123456789'], 'admin');
        $body = $this->body($this->get($this->app, '/shop/order', ['id' => $order['id']]));
        self::assertStringContainsString('반품·교환 신청', $body);
        self::assertStringContainsString('123456789', $body);
        self::assertStringNotContainsString('배송 처리', $body);
        $item = $this->shop->store->items($order['id'])[0];
        foreach (['return', 'exchange'] as $kind) {
            $response = $this->post($this->app, '/shop/order', $this->csrf(['id' => $order['id'], 'action' => 'claim', 'kind' => $kind,
                'item_id' => $item['id'], 'quantity' => '1', 'reason' => '옵션 변경 요청', 'replacement_id' => $product['variants'][1]['id'], 'request_key' => Store::id()]));
            self::assertSame(303, $response->getStatusCode(), $this->body($response));
        }
        self::assertCount(2, $this->shop->detail($order['id'], $user)['claims']);
        $this->signIn(false);
        self::assertSame(404, $this->get($this->app, '/shop/order', ['id' => $order['id']])->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testPreparingStoreAndSoldOutProductHaveActionableCustomerStates(array $config): void
    {
        $this->setupShop($config); $product = $this->product(); $this->signIn(false);
        $this->post($this->app, '/shop/cart', $this->csrf(['action' => 'add', 'variant_id' => $product['variants'][0]['id'], 'quantity' => '1']));
        $body = $this->body($this->get($this->app, '/shop/checkout'));
        self::assertStringContainsString('판매를 준비', $body);
        self::assertStringContainsString('<button disabled>', $body);
        self::assertStringNotContainsString('운영 정산', $body);
        $response = $this->post($this->app, '/shop/checkout', $this->csrf(['request_key' => array_key_last($_SESSION['shop_previews'])]));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $this->shop->orders(null));
        $this->shop->store->stock($product['variants'][0]['id'], -10, 'adjustment', 'admin');
        $body = $this->body($this->get($this->app, '/shop/product', ['id' => $product['id']]));
        self::assertStringContainsString('품절', $body);
        self::assertStringContainsString('<button disabled>장바구니 담기', $body);
        $body = $this->body($this->get($this->app, '/shop/catalog', ['q' => '없는검색어']));
        self::assertStringContainsString('검색 결과가 없습니다', $body);
        self::assertStringContainsString('전체 상품 보기', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testPublicNavigationAndMemberAccountFollowModuleActivation(array $config): void
    {
        $this->setupShop($config);
        $home = $this->body($this->get($this->app, '/'));
        self::assertStringContainsString('href="/shop"', $home);
        $this->product();
        $catalog = $this->body($this->get($this->app, '/shop/catalog'));
        self::assertStringContainsString('class="site-header"', $catalog);
        self::assertStringContainsString('shop.css?v=', $catalog);
        self::assertStringNotContainsString('운영 정산', $catalog);
        self::assertStringNotContainsString('상점 관리', $catalog);
        self::assertSame(200, $this->get($this->app, '/shop/')->getStatusCode());
        $this->signIn(false);
        self::assertStringContainsString('내 주문 확인', $this->body($this->get($this->app, '/account')));
        $manager = new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions'));
        $manager->setEnabled('modules/shop', false);
        self::assertStringNotContainsString('/shop/', $this->body($this->get($this->app, '/')));
        self::assertStringNotContainsString('내 주문 확인', $this->body($this->get($this->app, '/account')));
        self::assertSame(404, $this->get($this->app, '/shop/catalog')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCartResumesCheckoutAfterPasswordLoginWithSubdirectory(array $config): void
    {
        $this->setupShop($config); $this->openShop(); $product = $this->product();
        $password = bin2hex(random_bytes(16));
        $id = $this->app->users()->create('customer@example.test', password_hash($password, PASSWORD_DEFAULT), '구매자', false);
        $this->app->db()->execute('UPDATE ' . $this->app->db()->table('users') . ' SET email_verified = 1 WHERE id = ?', [$id]);
        $send = function (string $method, string $path, array $body = []) {
            return Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle(
                (new ServerRequestFactory())->createServerRequest($method, '/cms' . $path)->withParsedBody($body));
        };
        $send('GET', '/shop/catalog');
        $cart = [$product['variants'][0]['id'] => 2];
        self::assertSame(303, $send('POST', '/shop/cart', $this->csrf(['action' => 'add', 'variant_id' => array_key_first($cart), 'quantity' => '2']))->getStatusCode());
        self::assertSame('/cms/login', $send('GET', '/shop/checkout')->getHeaderLine('Location'));
        $login = $this->csrf(['email' => 'customer@example.test', 'password' => $password]);
        self::assertSame(422, $send('POST', '/login', array_replace($login, ['password' => 'incorrect']))->getStatusCode());
        self::assertSame('/cms/shop/checkout', $send('POST', '/login', $login)->getHeaderLine('Location'));
        self::assertSame($cart, $_SESSION['shop_cart']);
        self::assertArrayNotHasKey('login_destination', $_SESSION);
        self::assertStringContainsString('23,000', $this->body($send('GET', '/shop/checkout')));
        self::assertSame('/cms/', $send('POST', '/logout', $this->csrf([]))->getHeaderLine('Location'));
        $orderPath = '/shop/order?id=' . Store::id();
        self::assertSame('/cms/login', $send('GET', $orderPath)->getHeaderLine('Location'));
        self::assertSame('/cms' . $orderPath, $send('POST', '/login', $login)->getHeaderLine('Location'));
        self::assertSame(404, $send('GET', $orderPath)->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testCustomerOrdersFilterAndCancellationKeepOtherOrdersPrivate(array $config): void
    {
        $this->setupShop($config); $this->openShop(); $product = $this->product(); $user = $this->signIn(false);
        $customer = ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'consent' => '1'];
        $cart = [$product['variants'][0]['id'] => 1];
        $own = $this->shop->createOrder($user, $cart, $customer, 'inicis', Store::id(), 13000);
        $other = $this->shop->createOrder('other-user', $cart, $customer, 'inicis', Store::id(), 13000);
        $body = $this->body($this->get($this->app, '/shop/orders'));
        self::assertStringContainsString('customer-order', $body);
        self::assertStringContainsString($own['id'], $body);
        self::assertStringNotContainsString($other['id'], $body);
        self::assertStringNotContainsString($own['id'], $this->body($this->get($this->app, '/shop/orders', ['status' => 'shipped'])));
        self::assertSame(404, $this->post($this->app, '/shop/order', $this->csrf(['id' => $other['id'], 'action' => 'cancel-pending']))->getStatusCode());
        self::assertSame(303, $this->post($this->app, '/shop/order', $this->csrf(['id' => $own['id'], 'action' => 'cancel-pending']))->getStatusCode());
        self::assertSame('cancelled', $this->shop->detail($own['id'], $user)['status']);
        self::assertSame('pending', $this->shop->detail($other['id'], 'other-user')['status']);
        self::assertStringContainsString($own['id'], $this->body($this->get($this->app, '/shop/orders', ['status' => 'cancelled'])));
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach (array_reverse(\GnuCms\Modules\Shop\Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            foreach (array_keys(Settings::PROVIDERS) as $id) {
                $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_' . $id . '_transactions'));
                $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_' . $id . '_settings'));
            }
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testInstallationAuthorizationAndAllAdminPagesWithSubdirectory(array $config): void
    {
        $this->setupShop($config, false);
        self::assertSame(200, $this->get($this->app, '/shop/catalog')->getStatusCode());
        self::assertFalse($this->shop->ready());
        self::assertSame(401, $this->get($this->app, '/shop/admin')->getStatusCode());
        $this->signIn(false);
        foreach (['admin', 'products', 'settings', 'inventory', 'settlement', 'export'] as $page) self::assertSame(403, $this->get($this->app, '/shop/' . $page)->getStatusCode());
        $this->signIn(true);
        self::assertSame(403, $this->post($this->app, '/shop/admin', ['action' => 'install'])->getStatusCode());
        self::assertSame(303, $this->post($this->app, '/shop/admin', $this->csrf(['action' => 'install']))->getStatusCode());
        self::assertTrue($this->shop->ready());
        $p = $this->product();
        foreach (['/admin/shop', '/admin/shop/products', '/admin/shop/products/new', '/admin/shop/products/edit?id=' . $p['id'], '/admin/shop/settings', '/admin/shop/inventory', '/admin/shop/settlement', '/shop/catalog', '/shop/product?id=' . $p['id']] as $page) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $page);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode(), $page . ': ' . substr($this->body($response), 0, 200));
            self::assertStringContainsString('/cms/shop/', $this->body($response));
            if (str_starts_with($page, '/admin/shop')) self::assertStringContainsString('/cms/admin/shop/products', $this->body($response));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }
        foreach (array_keys(Settings::PROVIDERS) as $id) {
            self::assertSame(200, $this->get($this->app, '/plugins/payment-' . $id . '/settings')->getStatusCode());
            self::assertFalse((new Settings($this->app, $id))->ready());
            self::assertSame(200, $this->post($this->app, '/plugins/payment-' . $id . '/settings', $this->csrf(['action' => 'install']))->getStatusCode());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testMemberCheckoutSnapshotDuplicatePostAndOrderPrivacy(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $user = $this->signIn(false);
        $this->shop->saveSettings(['name' => '상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'live', 'open' => '1']);
        $settings = new Settings($this->app, 'inicis'); $settings->install();
        $settings->save('live', \GnuCms\Tests\Payment\Fixtures::config('inicis'));
        $settings->enable('live', true);
        self::assertSame(303, $this->post($this->app, '/shop/cart', $this->csrf(['action' => 'add', 'variant_id' => $p['variants'][0]['id'], 'quantity' => '2']))->getStatusCode());
        $response = $this->get($this->app, '/shop/checkout');
        self::assertSame(200, $response->getStatusCode()); self::assertStringContainsString('23,000', $this->body($response));
        self::assertStringContainsString('&lt;script&gt;상품&lt;/script&gt;', $this->body($response));
        $key = array_key_last($_SESSION['shop_previews']);
        $body = $this->csrf(['request_key' => $key, 'provider' => 'inicis', 'name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'address_detail' => '', 'consent' => '1']);
        $response = $this->post($this->app, '/shop/checkout', $body);
        self::assertSame(303, $response->getStatusCode()); $location = $response->getHeaderLine('Location');
        self::assertStringContainsString('/shop/order?id=', $location);
        self::assertSame($location, $this->post($this->app, '/shop/checkout', $body)->getHeaderLine('Location'));
        self::assertCount(1, $this->shop->orders($user));
        self::assertSame(200, $this->get($this->app, $location)->getStatusCode());
        $this->signIn(false);
        self::assertSame(404, $this->get($this->app, $location)->getStatusCode());
        self::assertStringNotContainsString('buyer@example.test', $this->body($this->get($this->app, $location)));
    }

    #[DataProvider('connectionProvider')]
    public function testForgedCallbacksAndMalformedInputsCannotMutateState(array $config): void
    {
        $this->setupShop($config); $this->signIn(true);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/shop/callback?id=' . Store::id())->withHeader('Content-Type', 'application/json');
        $request->getBody()->write('{"type":"Transaction.Paid","data":{"paymentId":"' . Store::id() . '"}}');
        self::assertSame(403, Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '')->handle($request)->getStatusCode());
        self::assertSame(422, $this->get($this->app, '/shop/catalog', ['q' => ['bad']])->getStatusCode());
        self::assertSame(403, $this->post($this->app, '/shop/settings', ['open' => '1'])->getStatusCode());
        self::assertSame([], $this->shop->orders(null));
    }

    #[DataProvider('connectionProvider')]
    public function testTossSettingsCheckoutAndAuthenticatedReturnOnlyPreparePost(array $config): void
    {
        $this->setupShop($config, base: '/cms');
        self::assertSame(401, $this->get($this->app, '/plugins/payment-toss/settings')->getStatusCode());
        $user = $this->signIn(true); $path = '/plugins/payment-toss/settings';
        self::assertSame(200, $this->post($this->app, $path, $this->csrf(['action' => 'install']))->getStatusCode());
        $credentials = \GnuCms\Tests\Payment\Fixtures::config('toss');
        $response = $this->post($this->app, $path, $this->csrf($credentials + ['action' => 'save', 'environment' => 'test']));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('admin-shell', $this->body($response));
        self::assertStringContainsString('extension-admin', $this->body($response));
        self::assertStringContainsString('extensions.css?v=', $this->body($response));
        self::assertStringContainsString('id="payment-secret_key"', $this->body($response));
        self::assertStringNotContainsString('font:16px/1.6 system-ui', $this->body($response));
        self::assertStringNotContainsString($credentials['secret_key'], $this->body($response));
        self::assertSame(200, $this->post($this->app, $path, $this->csrf(['action' => 'enable', 'environment' => 'test']))->getStatusCode());
        $settings = new Settings($this->app, 'toss'); self::assertTrue($settings->available('test'));
        $this->shop->saveSettings(['name' => '상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'test', 'open' => '1']);
        $p = $this->product();
        $shop = new Service($this->app, ['toss' => new \GnuCms\Payment\TossGateway($settings)]);
        $order = $shop->createOrder($user, [$p['variants'][0]['id'] => 1], ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'consent' => '1'], 'toss', Store::id(), 13000);
        $response = $this->post($this->app, '/shop/order', $this->csrf(['id' => $order['id'], 'action' => 'pay']));
        self::assertSame(200, $response->getStatusCode()); self::assertStringContainsString('https://js.tosspayments.com/v2/standard', $this->body($response));
        self::assertStringNotContainsString($credentials['secret_key'], $this->body($response));
        $query = ['id' => $order['id'], 'state' => \GnuCms\Payment\CallbackToken::create($this->app, $order),
            'paymentKey' => bin2hex(random_bytes(100)), 'orderId' => $order['id'], 'amount' => '13000'];
        $this->app->setIdentity(\GnuCms\Auth\Identity::guest());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms/shop/toss-return?' . http_build_query($query));
        $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode(), $this->body($response));
        self::assertStringContainsString('action="/cms/shop/callback?', $this->body($response));
        self::assertStringContainsString('name="paymentKey" value="' . $query['paymentKey'] . '"', $this->body($response));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control')); self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString("form-action 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('pending', $shop->store->get('shop_orders', $order['id'])['status']);
        self::assertSame('ready', (new \GnuCms\Payment\Journal($settings))->read($order['id'])['approval']);
        self::assertSame(403, $this->get($this->app, '/shop/toss-return', array_replace($query, ['state' => str_repeat('0', 64)]))->getStatusCode());
        foreach ([['amount' => '1'], ['orderId' => Store::id()], ['paymentKey' => ['bad']], ['paymentKey' => '"><script>']] as $bad) {
            self::assertSame(422, $this->get($this->app, '/shop/toss-return', array_replace($query, $bad))->getStatusCode());
        }
        $legacy = $this->get($this->app, '/modules/shop/toss-return', $query);
        self::assertSame(200, $legacy->getStatusCode());
        self::assertStringContainsString('action="/shop/callback?', $this->body($legacy));
        foreach (['/shop', '/modules/shop'] as $prefix) {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms' . $prefix . '/callback?' . http_build_query(['id' => $order['id'], 'state' => $query['state']]))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
            $request->getBody()->write(http_build_query(['paymentKey' => $query['paymentKey'], 'orderId' => $order['id'], 'amount' => '1']));
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/cms/shop/order?id=' . $order['id'], $response->getHeaderLine('Location'));
            self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        }
        self::assertSame('ready', (new \GnuCms\Payment\Journal($settings))->read($order['id'])['approval']);
    }
}
