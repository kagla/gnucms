<?php

declare(strict_types=1);

namespace GnuCms\Cms;

use HTMLPurifier;
use HTMLPurifier_Config;

final class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
            'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]',
            'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr',
            'a[href|title|target]', 'img[src|alt|title|width|height|style]',
            'div[class|style]', 'span[class|style]',
            'table[border|cellpadding|cellspacing|class|style|width]',
            'thead', 'tbody', 'tfoot', 'tr',
            'th[colspan|rowspan|scope|style|width]', 'td[colspan|rowspan|style|width]',
            'iframe[src|title|width|height]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'background-color', 'color', 'float', 'font-family', 'font-size', 'font-style',
            'font-weight', 'height', 'margin-left', 'margin-right', 'text-align',
            'text-decoration', 'vertical-align', 'width',
        ]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(?:www\.)?youtube(?:-nocookie)?\.com/embed/%');
        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(string $html): string
    {
        // Tagless editor HTML may still contain entities such as &middot;.
        // Preserve valid references while escaping markup and bare ampersands.
        if ($html !== '' && strip_tags($html) === $html) {
            return '<p>' . nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false)) . '</p>';
        }
        return $this->purifier->purify($html);
    }
}
