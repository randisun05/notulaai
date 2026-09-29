<?php

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

/**
 * Single choke point for HTML that will later be rendered raw (v-html in Vue,
 * {!! !!} in Blade emails): AI-generated summaries / email drafts and the
 * free-text meeting agenda. Strips scripts, event handlers, styles, iframes,
 * and unsafe URL schemes via the "ai_html" HTMLPurifier profile.
 */
class HtmlSanitizer
{
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return Purifier::clean($html, 'ai_html');
    }

    /**
     * HTML (rangkuman AI, agenda) → teks biasa untuk prompt AI & pencarian.
     */
    public static function toText(?string $html): string
    {
        $text = preg_replace(['/<\/(p|li|h\d|div)>/i', '/<br\s*\/?>/i'], "\n", (string) $html);

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($text))));
    }
}
