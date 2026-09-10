<?php

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_keeps_basic_formatting_tags(): void
    {
        $clean = HtmlSanitizer::clean('<h3>Judul</h3><p>Isi <b>tebal</b></p><ul><li>satu</li></ul>');

        $this->assertStringContainsString('<h3>Judul</h3>', $clean);
        $this->assertStringContainsString('<b>tebal</b>', $clean);
        $this->assertStringContainsString('<li>satu</li>', $clean);
    }

    public function test_strips_script_tags_and_their_contents(): void
    {
        $clean = HtmlSanitizer::clean('<p>aman</p><script>alert(document.cookie)</script>');

        $this->assertStringContainsString('<p>aman</p>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(document.cookie)', $clean);
    }

    public function test_strips_inline_event_handlers(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="steal()">x</p><img src="x" onerror="alert(1)">');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<img', $clean);
    }

    public function test_strips_iframes_and_style_tags(): void
    {
        $clean = HtmlSanitizer::clean('<iframe src="https://evil.test"></iframe><style>body{display:none}</style><p>ok</p>');

        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringContainsString('<p>ok</p>', $clean);
    }

    public function test_neutralizes_javascript_scheme_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">klik</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('klik', $clean);
    }

    public function test_null_or_blank_returns_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }
}
