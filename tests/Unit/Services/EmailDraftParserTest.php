<?php

namespace Tests\Unit\Services;

use App\Services\Meeting\EmailDraftParser;
use Tests\TestCase;

class EmailDraftParserTest extends TestCase
{
    private EmailDraftParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new EmailDraftParser();
    }

    public function test_parses_clean_json_object(): void
    {
        $raw = '{"subject":"Follow Up Rapat","body":"<p>Halo tim</p>"}';

        $draft = $this->parser->parse($raw, 'Fallback Subject');

        $this->assertSame('Follow Up Rapat', $draft['subject']);
        $this->assertSame('<p>Halo tim</p>', $draft['body']);
    }

    public function test_parses_json_wrapped_in_code_fence(): void
    {
        $raw = "```json\n{\"subject\":\"Reminder\",\"body\":\"<p>Jangan lupa</p>\"}\n```";

        $draft = $this->parser->parse($raw, 'Fallback Subject');

        $this->assertSame('Reminder', $draft['subject']);
        $this->assertSame('<p>Jangan lupa</p>', $draft['body']);
    }

    public function test_missing_subject_falls_back_to_provided_default(): void
    {
        $raw = '{"body":"<p>Isi email tanpa subjek</p>"}';

        $draft = $this->parser->parse($raw, 'Fallback Subject');

        $this->assertSame('Fallback Subject', $draft['subject']);
        $this->assertSame('<p>Isi email tanpa subjek</p>', $draft['body']);
    }

    public function test_non_json_response_becomes_body_with_fallback_subject_instead_of_being_dropped(): void
    {
        $raw = 'Maaf, ini bukan format JSON yang diminta.';

        $draft = $this->parser->parse($raw, 'Fallback Subject');

        $this->assertSame('Fallback Subject', $draft['subject']);
        $this->assertStringContainsString('Maaf, ini bukan format JSON', $draft['body']);
    }
}
