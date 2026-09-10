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
}
