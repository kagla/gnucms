<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use Psr\Http\Message\UploadedFileInterface;

/** 상품별 이미지 목록과 대표 이미지는 한 트랜잭션에서 변경한다. */
final class ProductImages
{
    public const MAX_IMAGES = 20;
    private Store $store;
    private Images $files;
    private PackageSchema $schema;

    public function __construct(App $app)
    {
        $this->store = new Store($app->db());
        $this->files = new Images($app);
        $this->schema = new PackageSchema($app->db(), $app->storageDir());
    }

    public function ready(): bool { return $this->schema->current(Schema::KEY, Schema::VERSION) || $this->schema->current(Schema::KEY, 3); }

    public function listing(array $product): array
    {
        if ($this->ready()) {
            $rows = $this->store->db->select('SELECT * FROM ' . $this->store->db->table('shop_product_images') . ' WHERE product_id = ? ORDER BY sort_order, id', [$product['id']]);
            if ($rows !== []) return $rows;
        }
        return $product['image'] === '' ? [] : [['id' => $product['id'], 'product_id' => $product['id'], 'filename' => $product['image'], 'sort_order' => 0]];
    }

    /** @return list<string> 새로 저장한 파일명. 외부 트랜잭션 실패 시 정리에 사용한다. */
    public function append(string $productId, array $uploads, int $version): array
    {
        if (!array_is_list($uploads) || $uploads === [] || count($uploads) > self::MAX_IMAGES) {
            throw DomainError::validation(['images' => '이미지는 1 ~ 20장까지 선택해 주세요.']);
        }
        foreach ($uploads as $upload) if (!$upload instanceof UploadedFileInterface) {
            throw DomainError::validation(['images' => '이미지 파일을 다시 선택해 주세요.']);
        }
        $written = [];
        try {
            $this->store->db->transaction(function () use ($productId, $uploads, $version, &$written): void {
                $product = $this->lock($productId, $version);
                $existing = $this->listing($product);
                if (count($existing) + count($uploads) > self::MAX_IMAGES) {
                    throw DomainError::validation(['images' => '상품 이미지는 기존 이미지를 포함해 최대 20장까지 등록할 수 있습니다.']);
                }
                foreach ($uploads as $offset => $upload) {
                    $filename = $this->files->save($upload);
                    $written[] = $filename;
                    $this->store->insert('shop_product_images', ['id' => substr($filename, 0, 32), 'product_id' => $productId,
                        'filename' => $filename, 'sort_order' => count($existing) + $offset]);
                }
                if ($existing === []) $this->store->update('shop_products', $productId, ['image' => $written[0]]);
            });
        } catch (\Throwable $error) {
            $this->discard($written);
            throw $error;
        }
        return $written;
    }

    public function discard(array $filenames): void
    {
        foreach ($filenames as $filename) $this->files->discard($filename);
    }

    /** 기존 이미지 ID와 이번에 선택한 파일 번호를 함께 받는다. */
    public static function order(mixed $order): array
    {
        if (!is_array($order) || !array_is_list($order) || count($order) > self::MAX_IMAGES) {
            throw DomainError::validation(['images' => '이미지 순서를 확인해 주세요.']);
        }
        foreach ($order as $id) if (!is_string($id) || !preg_match('/^(?:[a-f0-9]{32}|new:(?:[0-9]|1[0-9]))$/D', $id)) {
            throw DomainError::validation(['images' => '이미지 순서를 확인해 주세요.']);
        }
        if (count(array_unique($order)) !== count($order)) throw DomainError::validation(['images' => '이미지 순서에 중복된 항목이 있습니다.']);
        return $order;
    }

    /** 상품 저장 트랜잭션 안에서 호출하며 작성한 파일을 호출자에게 전달한다. */
    public function save(string $productId, array $uploads, ?array $order, array &$written): void
    {
        if ($uploads !== []) $written = $this->append($productId, $uploads, (int) $this->store->get('shop_products', $productId)['version']);
        if ($order === null) return;
        $newIds = array_map(static fn ($filename) => substr($filename, 0, 32), $written);
        $hasNew = false;
        foreach ($order as &$id) if (str_starts_with($id, 'new:')) {
            $hasNew = true;
            $id = $newIds[(int) substr($id, 4)] ?? throw DomainError::validation(['images' => '새 이미지를 다시 선택해 주세요.']);
        }
        unset($id);
        // 자바스크립트 없이 제출한 파일도 선택 순서대로 추가한다.
        if (!$hasNew) $order = array_merge($order, $newIds);
        $product = $this->store->get('shop_products', $productId);
        if ($order !== [] || $this->listing($product) !== []) $this->reorder($productId, $order, (int) $product['version']);
    }

    public static function ids(mixed $ids): array
    {
        if (!is_array($ids) || !array_is_list($ids) || $ids === [] || count($ids) > self::MAX_IMAGES) {
            throw DomainError::validation(['images' => '이미지 순서를 확인해 주세요.']);
        }
        foreach ($ids as $id) Input::id($id);
        if (count(array_unique($ids)) !== count($ids)) throw DomainError::validation(['images' => '이미지 순서에 중복된 항목이 있습니다.']);
        return $ids;
    }

    public function reorder(string $productId, mixed $ids, int $version): void
    {
        $ids = self::ids($ids);
        $this->store->db->transaction(function () use ($productId, $ids, $version): void {
            $product = $this->lock($productId, $version);
            $images = array_column($this->listing($product), null, 'id');
            if (count($images) !== count($ids) || array_diff($ids, array_keys($images)) !== []) {
                throw DomainError::validation(['images' => '이 상품의 모든 이미지를 빠짐없이 포함해야 합니다. 새로고침 후 다시 정렬해 주세요.']);
            }
            foreach ($ids as $position => $id) $this->store->update('shop_product_images', $id, ['sort_order' => $position]);
            $this->store->update('shop_products', $productId, ['image' => $images[$ids[0]]['filename']]);
        });
    }

    private function lock(string $id, int $version): array
    {
        if (!$this->ready()) throw DomainError::validation(['images' => '쇼핑몰 이미지 데이터를 먼저 업데이트해 주세요.']);
        if ($this->store->db->execute('UPDATE ' . $this->store->db->table('shop_products') . ' SET version = version + 1 WHERE id = ? AND version = ?', [Input::id($id), $version]) !== 1) {
            throw DomainError::validation(['images' => '상품 또는 이미지가 변경되었습니다. 새로고침 후 다시 시도해 주세요.']);
        }
        $product = $this->store->get('shop_products', $id);
        if ($product['image'] !== '' && $this->store->db->selectOne('SELECT id FROM ' . $this->store->db->table('shop_product_images') . ' WHERE product_id = ?', [$id]) === null) {
            $this->store->insert('shop_product_images', ['id' => $id, 'product_id' => $id, 'filename' => $product['image'], 'sort_order' => 0]);
        }
        return $product;
    }
}
