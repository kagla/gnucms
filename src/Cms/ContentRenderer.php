<?php

declare(strict_types=1);

namespace GnuCms\Cms;

/**
 * 저장된 본문을 화면에 내보내기 직전에 손질한다.
 *
 * 정화(HtmlSanitizer)를 거친 뒤, 본문에 넣은 사진을 축소본으로 바꾸고
 * 원본은 눌렀을 때만 받아 가도록 링크로 감싼다. 독립된 유튜브 주소·링크는
 * 반응형 플레이어로 표시한다. 저장된 내용은 건드리지 않는다.
 */
final class ContentRenderer
{
    /** @var HtmlSanitizer */
    private $sanitizer;

    public function __construct(HtmlSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function render(string $raw): string
    {
        return YouTubeEmbed::render($this->zoomable($this->sanitizer->clean($raw)));
    }

    /**
     * 편집기가 올린 사진을 `<a href="원본"><img src="축소본"></a>` 로 바꾼다.
     *
     * 이미 링크 안에 있는 사진은 건드리지 않는다. 링크를 겹쳐 걸면
     * 글쓴이가 걸어 둔 주소를 덮어쓰게 된다.
     */
    private function zoomable(string $html): string
    {
        return (string) preg_replace_callback(
            '#<a\b[^>]*>.*?</a>|<img\b[^>]*>#is',
            function (array $m): string {
                $tag = $m[0];
                if (strncasecmp($tag, '<img', 4) !== 0) {
                    return $tag;
                }
                if (preg_match('#\bsrc\s*=\s*"([^"]+)"#i', $tag, $src) !== 1) {
                    return $tag;
                }

                $original = html_entity_decode($src[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $small = self::variantUrl($original, 'view');
                if ($small === null) {
                    return $tag;
                }

                $tag = (string) preg_replace(
                    '#\bsrc\s*=\s*"[^"]*"#i',
                    'src="' . htmlspecialchars($small, ENT_QUOTES, 'UTF-8') . '"',
                    $tag,
                    1
                );
                if (stripos($tag, ' loading=') === false) {
                    $tag = substr($tag, 0, -1) . ' loading="lazy" decoding="async">';
                }

                return '<a class="zoom" href="' . htmlspecialchars($original, ENT_QUOTES, 'UTF-8') . '"'
                    . ' target="_blank" rel="noopener" data-zoom>' . $tag . '</a>';
            },
            $html
        );
    }

    /**
     * 편집기 사진 주소를 같은 폴더의 축소본 주소로 바꾼다.
     *
     * 우리 편집기가 올린 주소가 아니면 null 이다. 본문에는 다른 사이트 주소도
     * 들어올 수 있는데, 그것을 우리 규칙으로 고쳐 봐야 없는 파일만 가리킨다.
     */
    public static function variantUrl(string $url, string $variant): ?string
    {
        $pattern = '#^((?:/[^/?\#]+)*?/media/editor/(?:[a-f0-9]{32}|[0-9]{4}/[0-9]{1,2})/)'
            . '([a-f0-9]{32})(\.(?:jpg|png|gif|webp))$#i';
        if (preg_match($pattern, $url, $m) !== 1) {
            return null;
        }

        return $m[1] . $m[2] . '-' . $variant . $m[3];
    }
}
