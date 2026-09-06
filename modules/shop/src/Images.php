<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\App;
use GnuCms\Error\DomainError;
use Psr\Http\Message\UploadedFileInterface;

final class Images
{
    private string $directory;
    public function __construct(private App $app)
    {
        $this->directory = rtrim((string) $app->config('uploads.dir', $app->storageDir() . '/uploads'), '/') . '/shop';
    }

    public function save(UploadedFileInterface $upload): string
    {
        if ($upload->getError() !== UPLOAD_ERR_OK || ($upload->getSize() ?? 0) < 1 || ($upload->getSize() ?? 0) > 5242880) {
            throw DomainError::validation(['image' => '5MB 이하의 JPG·PNG·WebP 이미지를 선택해 주세요.']);
        }
        $stream = $upload->getStream();
        if ($stream->isSeekable()) $stream->rewind();
        $bytes = $stream->read(5242881);
        $info = @getimagesizefromstring($bytes);
        $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (strlen($bytes) > 5242880 || $info === false || !isset($extensions[$info[2]]) || $info[0] > 8000 || $info[1] > 8000) {
            throw DomainError::validation(['image' => '가로·세로 8,000px 이하의 JPG·PNG·WebP 이미지를 선택해 주세요.']);
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) throw DomainError::serviceUnavailable('이미지 폴더를 만들지 못했습니다.');
        $name = Store::id() . '.' . $extensions[$info[2]];
        if (file_put_contents($this->directory . '/' . $name, $bytes, LOCK_EX) !== strlen($bytes)) throw DomainError::serviceUnavailable('이미지를 저장하지 못했습니다.');
        return $name;
    }

    public function response(string $name, $response)
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $name, $m) || !is_file($this->directory . '/' . $name)) throw DomainError::notFound('이미지를 찾을 수 없습니다.');
        $response->getBody()->write(file_get_contents($this->directory . '/' . $name));
        return $response->withHeader('Content-Type', ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$m[1]])
            ->withHeader('X-Content-Type-Options', 'nosniff')->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
