<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlCleaner
{
    /**
     * Rich text from the listing editor is rendered with v-html, so it is
     * reduced to safe elements before it is stored.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)->allowSafeElements()->withMaxInputLength(50_000),
        );

        return $sanitizer->sanitize($html);
    }
}
