<?php

namespace Tests\Unit\Services;

use App\Services\Meeting\ActionItemsParser;
use Tests\TestCase;

class ActionItemsParserTest extends TestCase
{
    private ActionItemsParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ActionItemsParser();
    }

    public function test_parses_clean_json_array(): void
    {
        $raw = '[{"title":"Kirim proposal","assignee_name":"Budi","deadline":"2026-08-01"}]';

        $items = $this->parser->parse($raw);

        $this->assertCount(1, $items);
        $this->assertSame('Kirim proposal', $items[0]['title']);
        $this->assertSame('Budi', $items[0]['assignee_name']);
        $this->assertSame('2026-08-01', $items[0]['deadline']);
    }

    public function test_parses_json_wrapped_in_markdown_code_fence(): void
    {
        $raw = "```json\n[{\"title\":\"Review dokumen\",\"assignee_name\":null,\"deadline\":null}]\n```";

        $items = $this->parser->parse($raw);

        $this->assertCount(1, $items);
        $this->assertSame('Review dokumen', $items[0]['title']);
        $this->assertNull($items[0]['assignee_name']);
        $this->assertNull($items[0]['deadline']);
    }

    public function test_empty_array_returns_no_items(): void
    {
        $this->assertSame([], $this->parser->parse('[]'));
    }

    public function test_garbage_response_returns_no_items_instead_of_throwing(): void
    {
        $this->assertSame([], $this->parser->parse('Maaf, saya tidak bisa membantu dengan itu.'));
    }

    public function test_items_without_title_are_filtered_out(): void
    {
        $raw = '[{"assignee_name":"Budi","deadline":"2026-08-01"},{"title":"Valid item"}]';

        $items = $this->parser->parse($raw);

        $this->assertCount(1, $items);
        $this->assertSame('Valid item', $items[0]['title']);
    }

    public function test_unparseable_deadline_is_kept_as_null_without_dropping_item(): void
    {
        $raw = '[{"title":"Task tanpa deadline jelas","deadline":"secepatnya"}]';

        $items = $this->parser->parse($raw);

        $this->assertCount(1, $items);
        $this->assertNull($items[0]['deadline']);
    }

    public function test_extracts_json_array_embedded_in_extra_prose(): void
    {
        $raw = "Berikut action item yang saya temukan:\n[{\"title\":\"Follow up client\"}]\nSemoga membantu.";

        $items = $this->parser->parse($raw);

        $this->assertCount(1, $items);
        $this->assertSame('Follow up client', $items[0]['title']);
    }
}
