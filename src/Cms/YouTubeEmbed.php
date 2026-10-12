<?php

declare(strict_types=1);

namespace GnuCms\Cms;

/** Converts standalone YouTube lines in already-sanitized HTML, without changing stored content. */
final class YouTubeEmbed
{
    public static function render(string $html): string
    {
        // CKEditor can save bare text and <br> without a surrounding paragraph.
        if (preg_match('~<(?:p|div|pre|code|iframe|ul|ol|table|blockquote)\b~i', $html) !== 1) {
            return self::lines($html, '', '');
        }

        return (string) preg_replace_callback(
            '~<(?:pre|code|iframe)\b[^>]*>.*?</(?:pre|code|iframe)\s*>'
            . '|(?<open><p\b[^>]*>)(?<body>.*?)(?<close></p\s*>)'
            . '|(?<divopen><div\b[^>]*>)(?<divbody>(?:(?!<(?:p|div|pre|code|iframe)\b).)*?)(?<divclose></div\s*>)~is',
            static function (array $match): string {
                if (($match['open'] ?? '') !== '') {
                    return self::lines($match['body'], $match['open'], $match['close']);
                }
                if (($match['divopen'] ?? '') !== '') {
                    return self::lines($match['divbody'], $match['divopen'], $match['divclose']);
                }
                return $match[0];
            },
            $html
        );
    }

    private static function lines(string $body, string $open, string $close): string
    {
        if (preg_match('~<(?:pre|code|iframe)\b~i', $body) === 1) {
            return $open . $body . $close;
        }
        $lines = preg_split('~<br\s*/?>~i', $body) ?: [$body];
        $output = '';
        $pending = [];
        $changed = false;
        foreach ($lines as $line) {
            $player = self::line($line);
            if ($player === null) {
                $pending[] = $line;
                continue;
            }
            $changed = true;
            if (implode('', $pending) !== '') {
                $output .= $open . implode('<br>', $pending) . $close;
            }
            $pending = [];
            $output .= $player;
        }
        if (!$changed) {
            return $open . $body . $close;
        }
        if (implode('', $pending) !== '') {
            $output .= $open . implode('<br>', $pending) . $close;
        }
        return $output;
    }

    private static function line(string $html): ?string
    {
        $html = trim($html);
        // Images, literal code and nested blocks must never become video players.
        if (preg_match('~<(?:img|pre|code|iframe|p|div|ul|ol|li|table|blockquote|h[1-6])\b~i', $html) === 1) {
            return null;
        }

        $label = self::trimLine(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $url = $label;
        if (stripos($html, '<a') !== false) {
            if (preg_match('~\A<a\b[^>]*\bhref="([^"]+)"[^>]*>[^<]*(?:<(?:strong|b|em|i|span)\b[^>]*>[^<]*</(?:strong|b|em|i|span)>[^<]*)*</a>\z~is', $html, $anchor) !== 1) {
                return null;
            }
            $url = html_entity_decode($anchor[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $video = self::video($url);
        if ($video === null) {
            return null;
        }

        [$id, $start] = $video;
        $src = 'https://www.youtube-nocookie.com/embed/' . $id;
        $watch = 'https://www.youtube.com/watch?v=' . $id;
        if ($start > 0) {
            $src .= '?start=' . $start;
            $watch .= '&t=' . $start;
        }
        if ($label === $url || $label === '' || mb_strlen($label) > 120) {
            $label = 'YouTube에서 영상 보기';
        }
        $title = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Generated attributes are fixed; user URLs, classes and markup are never interpolated.
        // Inline sizing also works in themes which replace the default stylesheet.
        return '<div class="gnucms-youtube" style="position:relative;width:100%;padding-top:56.25%;margin:1em 0">'
            . '<iframe src="' . $src . '" title="' . $title . '" width="560" height="315"'
            . ' style="position:absolute;inset:0;width:100%;height:100%;border:0" loading="lazy"'
            . ' referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media;picture-in-picture;fullscreen" allowfullscreen></iframe></div>'
            . '<p><a href="' . htmlspecialchars($watch, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $title . '</a></p>';
    }

    /** @return array{string,int}|null */
    private static function video(string $url): ?array
    {
        $url = self::trimLine($url);
        if (preg_match('~[\s\x{00a0}]~u', $url) === 1) {
            return null;
        }
        if (preg_match('~\A(?:www\.)?(?:youtube\.com|youtu\.be)/~i', $url) === 1) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if (rtrim($path, '/') === '/watch') {
                $id = $query['v'] ?? null;
            } elseif (preg_match('~\A/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})/?\z~', $path, $m) === 1) {
                $id = $m[1];
            } else {
                return null;
            }
        } else {
            return null;
        }
        if (!is_string($id) || preg_match('~\A[A-Za-z0-9_-]{11}\z~', $id) !== 1) {
            return null;
        }
        $time = $query['start'] ?? $query['t'] ?? '';
        $start = 0;
        if (is_string($time) && preg_match('~\A\d{1,6}s?\z~', $time) === 1) {
            $start = (int) $time;
        } elseif (is_string($time) && $time !== '' && preg_match('~\A(?:(\d{1,3})h)?(?:(\d{1,3})m)?(?:(\d{1,3})s)?\z~', $time, $m) === 1) {
            $start = (int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
        }
        return [$id, min($start, 604800)];
    }

    private static function trimLine(string $text): string
    {
        return (string) preg_replace('~\A[\s\x{00a0}]+|[\s\x{00a0}]+\z~u', '', $text);
    }
}
