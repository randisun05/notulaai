<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Forum\MentionParser;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MentionParserTest extends TestCase
{
    private MentionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new MentionParser();
    }

    private function makeUser(int $id, string $name): User
    {
        $user = new User();
        $user->id = $id;
        $user->name = $name;

        return $user;
    }

    public function test_extracts_mentioned_user_by_full_name(): void
    {
        $budi = $this->makeUser(1, 'Budi Santoso');
        $citra = $this->makeUser(2, 'Citra Lestari');

        $result = $this->parser->extract('Halo @Budi Santoso, tolong cek ini.', new Collection([$budi, $citra]));

        $this->assertCount(1, $result);
        $this->assertSame(1, $result->first()->id);
    }

    public function test_mention_matching_is_case_insensitive(): void
    {
        $budi = $this->makeUser(1, 'Budi Santoso');

        $result = $this->parser->extract('halo @BUDI SANTOSO apa kabar', new Collection([$budi]));

        $this->assertCount(1, $result);
    }

    public function test_no_mention_returns_empty_collection(): void
    {
        $budi = $this->makeUser(1, 'Budi Santoso');

        $result = $this->parser->extract('Tidak ada yang disebut di sini.', new Collection([$budi]));

        $this->assertCount(0, $result);
    }

    public function test_multiple_mentions_are_all_detected(): void
    {
        $budi = $this->makeUser(1, 'Budi Santoso');
        $citra = $this->makeUser(2, 'Citra Lestari');

        $result = $this->parser->extract('@Budi Santoso dan @Citra Lestari mohon review.', new Collection([$budi, $citra]));

        $this->assertCount(2, $result);
    }

    public function test_name_without_at_symbol_is_not_matched(): void
    {
        $budi = $this->makeUser(1, 'Budi Santoso');

        $result = $this->parser->extract('Budi Santoso akan hadir.', new Collection([$budi]));

        $this->assertCount(0, $result);
    }
}
