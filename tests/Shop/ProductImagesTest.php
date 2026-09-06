<?php

declare(strict_types=1);

namespace GnuCms\Tests\Shop;

use GnuCms\App;
use GnuCms\Db\Schema as CoreSchema;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use GnuCms\Modules\Shop\Images;
use GnuCms\Modules\Shop\Schema;
use GnuCms\Modules\Shop\Service;
use GnuCms\Modules\Shop\Store;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\UploadedFile;

require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';

final class ProductImagesTest extends DatabaseTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=';
    private App $app;
    private Service $shop;
    private string $root;

    private function setupShop(array $config): string
    {
        $this->root = sys_get_temp_dir() . '/gnucms-images-' . Store::id();
        mkdir($this->root, 0700, true);
        $config['prefix'] = 'im' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new CoreSchema($this->app->db()))->create();
        $this->shop = new Service($this->app); $this->shop->install();
        return $this->shop->catalog->save(['name' => '이미지 상품', 'price' => 1000, 'stock' => 3, 'active' => '1']);
    }

    private function upload(?string $bytes = null, int $error = UPLOAD_ERR_OK, ?int $size = null): UploadedFile
    {
        $bytes ??= base64_decode(self::PNG);
        $path = $this->root . '/source-' . Store::id(); file_put_contents($path, $bytes);
        return new UploadedFile($path, 'input.png', 'image/png', $size ?? strlen($bytes), $error);
    }

    private function rejected(callable $action): void
    {
        try { $action(); self::fail('잘못된 이미지 변경을 거절해야 합니다.'); }
        catch (DomainError $error) { self::assertContains($error->status(), [404, 422]); }
    }

    #[DataProvider('connectionProvider')]
    public function testProductRegistrationAndEditingSaveImagesAndOptionsTogether(array $config): void
    {
        $this->setupShop($config);
        $input = ['name' => '한 번에 등록', 'active' => '1', 'gallery_present' => '1', 'image_order' => ['new:1', 'new:0'],
            'option1_name' => '색상', 'option1_values' => '검정,흰색', 'variants' => [
                ['option1' => '검정', 'price' => '12000', 'stock' => '3'], ['option1' => '흰색', 'price' => '14000', 'stock' => '0'],
            ]];
        $id = $this->shop->catalog->save($input, [$this->upload(), $this->upload()]);
        $product = $this->shop->catalog->product($id);
        self::assertCount(2, $product['images']);
        self::assertSame($product['images'][0]['filename'], $product['image']);
        self::assertSame([12000, 14000], array_map('intval', array_column($product['variants'], 'price')));
        self::assertSame([3, 0], array_map('intval', array_column($product['variants'], 'stock')));
        $input = array_replace($input, ['id' => $id, 'version' => $product['version'], 'name' => '이미지까지 수정',
            'variants' => array_map(static fn ($v) => array_intersect_key($v, array_flip(['id', 'version', 'option1', 'option2', 'price', 'stock'])), $product['variants']),
            'image_order' => ['new:0', $product['images'][1]['id'], $product['images'][0]['id']]]);
        $input['variants'][0]['stock'] = '7';
        $this->shop->catalog->save($input, [$this->upload()]);
        $saved = $this->shop->catalog->product($id);
        self::assertSame('이미지까지 수정', $saved['name']);
        self::assertCount(3, $saved['images']);
        self::assertSame($product['images'][1]['id'], $saved['images'][1]['id']);
        self::assertSame($saved['images'][0]['filename'], $saved['image']);
        self::assertSame(7, (int) $saved['variants'][0]['stock']);
        $this->rejected(fn () => $this->shop->catalog->save($input, [$this->upload()]));
        self::assertSame($saved, $this->shop->catalog->product($id));
        self::assertCount(3, glob($this->root . '/uploads/shop/*'));
    }

    #[DataProvider('connectionProvider')]
    public function testFailedCombinedSaveRollsBackProductStockAndFiles(array $config): void
    {
        $id = $this->setupShop($config);
        $this->shop->images->append($id, [$this->upload()], 1);
        $product = $this->shop->catalog->product($id);
        $files = glob($this->root . '/uploads/shop/*');
        $stock = $this->app->db()->select('SELECT * FROM ' . $this->app->db()->table('shop_stock'));
        $input = ['id' => $id, 'version' => $product['version'], 'name' => '실패한 변경', 'active' => '1', 'gallery_present' => '1',
            'image_order' => [$product['images'][0]['id'], 'new:0'], 'variants' => [
                ['id' => $product['variants'][0]['id'], 'version' => $product['variants'][0]['version'], 'price' => '9000', 'stock' => '8'],
            ]];
        foreach ([[$this->upload(), $this->upload('bad')], [$this->upload(), $this->upload()]] as $batch) {
            // 두 번째 경우는 파일 저장 성공 후 순서에서 새 파일 하나가 빠져 실패한다.
            $this->rejected(fn () => $this->shop->catalog->save($input, $batch));
            self::assertSame($product, $this->shop->catalog->product($id));
            self::assertSame($files, glob($this->root . '/uploads/shop/*'));
            self::assertSame($stock, $this->app->db()->select('SELECT * FROM ' . $this->app->db()->table('shop_stock')));
        }
        foreach ([['new:19'], ['new:0', Store::id()], ['new:0', 'new:0'], [['new:0']]] as $order) {
            $this->rejected(fn () => $this->shop->catalog->save(['name' => '실패한 등록', 'stock' => '9', 'gallery_present' => '1', 'image_order' => $order], [$this->upload()]));
            self::assertCount(1, $this->shop->catalog->listing(true));
            self::assertSame($files, glob($this->root . '/uploads/shop/*'));
        }
    }

    #[DataProvider('connectionProvider')]
    public function testMultipleUploadsAppendAndReorderingChangesOnlyThisProductsCover(array $config): void
    {
        $id = $this->setupShop($config);
        $version = (int) $this->shop->catalog->product($id)['version'];
        $this->shop->images->append($id, [$this->upload(), $this->upload()], $version);
        $product = $this->shop->catalog->product($id);
        self::assertCount(2, $product['images']);
        self::assertSame($product['images'][0]['filename'], $product['image']);
        $this->shop->images->append($id, [$this->upload()], (int) $product['version']);
        $product = $this->shop->catalog->product($id);
        $order = array_reverse(array_column($product['images'], 'id'));
        $this->shop->images->reorder($id, $order, (int) $product['version']);
        $reordered = $this->shop->catalog->product($id);
        self::assertSame($order, array_column($reordered['images'], 'id'));
        self::assertSame($product['images'][2]['filename'], $reordered['image']);
        self::assertSame($reordered['image'], $this->shop->catalog->listing()[0]['image']);
        self::assertSame($reordered['image'], $this->shop->catalog->quote([$product['variants'][0]['id'] => 1])['items'][0]['image']);
        self::assertSame($product['variants'], $reordered['variants']);
        foreach ($reordered['images'] as $image) self::assertSame(base64_decode(self::PNG), file_get_contents($this->root . '/uploads/shop/' . $image['filename']));
        self::assertContains('shop_product_images', (new PackageSchema($this->app->db(), $this->root))->backupTables());
    }

    #[DataProvider('connectionProvider')]
    public function testInvalidBatchesAndStaleOrForgedOrdersPreserveFilesAndDatabase(array $config): void
    {
        $id = $this->setupShop($config);
        $this->shop->images->append($id, [$this->upload(), $this->upload()], 1);
        $product = $this->shop->catalog->product($id); $version = (int) $product['version'];
        $files = glob($this->root . '/uploads/shop/*');
        foreach ([$this->upload('not an image'), $this->upload(error: UPLOAD_ERR_PARTIAL), $this->upload(size: 5242881)] as $bad) {
            $this->rejected(fn () => $this->shop->images->append($id, [$this->upload(), $bad], $version));
            self::assertSame($files, glob($this->root . '/uploads/shop/*'));
            self::assertSame($product, $this->shop->catalog->product($id));
        }
        $this->rejected(fn () => $this->shop->images->append($id, [], $version));
        $this->rejected(fn () => $this->shop->images->append($id, [[$this->upload()]], $version));
        $this->rejected(fn () => $this->shop->images->append($id, array_fill(0, 19, $this->upload()), $version));
        $other = $this->shop->catalog->save(['name' => '다른 상품']);
        $this->shop->images->append($other, [$this->upload()], 1);
        $otherId = $this->shop->catalog->product($other, true)['images'][0]['id'];
        $ids = array_column($product['images'], 'id');
        foreach ([[], [$ids[0]], [$ids[0], $ids[0]], [$ids[0], $otherId], [$ids[0], '../file'], [$ids[0], [$ids[1]]]] as $bad) {
            $this->rejected(fn () => $this->shop->images->reorder($id, $bad, $version));
            self::assertSame($product, $this->shop->catalog->product($id));
        }
        $this->shop->images->reorder($id, array_reverse($ids), $version);
        $fresh = $this->shop->catalog->product($id);
        $this->rejected(fn () => $this->shop->images->reorder($id, $ids, $version));
        $this->rejected(fn () => $this->shop->images->append($id, [$this->upload()], $version));
        self::assertSame($fresh, $this->shop->catalog->product($id));
        self::assertCount(3, glob($this->root . '/uploads/shop/*'));
    }

    #[DataProvider('connectionProvider')]
    public function testLegacyCoverMigrationAndInterruptedRetryKeepExistingImages(array $config): void
    {
        $id = $this->setupShop($config);
        $legacy = (new Images($this->app))->save($this->upload());
        $this->shop->store->update('shop_products', $id, ['image' => $legacy]);
        $db = $this->app->db();
        $db->execute('DROP TABLE ' . $db->table('shop_product_images'));
        $oldTables = array_values(array_diff(Schema::TABLES, ['shop_product_images']));
        $db->update('extension_schemas', ['schema_version' => 2, 'table_names' => json_encode($oldTables)], 'package_key = :key', ['key' => Schema::KEY]);
        self::assertTrue($this->shop->ready());
        self::assertFalse($this->shop->images->ready());
        self::assertSame($legacy, $this->shop->catalog->product($id)['images'][0]['filename']);
        $this->shop->install();
        self::assertTrue($this->shop->images->ready());
        self::assertCount(1, $this->shop->catalog->product($id)['images']);
        $this->shop->images->append($id, [$this->upload()], 1);
        $product = $this->shop->catalog->product($id);
        $this->shop->images->reorder($id, array_reverse(array_column($product['images'], 'id')), (int) $product['version']);
        $saved = $this->shop->catalog->product($id);
        $db->update('extension_schemas', ['schema_version' => 2, 'state' => 'failed'], 'package_key = :key', ['key' => Schema::KEY]);
        $this->shop->install(); $this->shop->install();
        self::assertSame($saved, $this->shop->catalog->product($id));
        self::assertFileExists($this->root . '/uploads/shop/' . $legacy);
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach (array_reverse(Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            (new CoreSchema($this->app->db()))->drop();
        }
        if (isset($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }
}
