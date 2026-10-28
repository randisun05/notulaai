<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasFactory, ScopedToUnit;

    protected $fillable = [
        'user_id',
        'unit_id', // Ditambahkan
        'title',
        'date',
        'agenda',
        'attendees',
        'status',
        'transcript',
        'summary',
        'source_file_path', // Ditambahkan di sini
    ];

    /** Columns covered by the meetings_search_fulltext index (MySQL only). */
    private const FULLTEXT_COLUMNS = ['agenda', 'transcript', 'summary'];

    /** InnoDB's default innodb_ft_min_token_size; shorter words are not indexed. */
    private const FULLTEXT_MIN_WORD = 3;

    /**
     * Free-text search over title, agenda, transcript and summary.
     *
     * Everything is wrapped in one nested where() so the ORs can never escape
     * an outer constraint such as visibleTo()'s unit filter. On MySQL the long
     * text columns go through the FULLTEXT index (word-prefix match, every
     * word required); elsewhere (SQLite tests) and for searches with no word
     * long enough to be indexed, it falls back to a substring LIKE.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        // "!" as LIKE escape character works the same on MySQL, SQLite and Postgres.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        preg_match_all('/[\p{L}\p{N}]+/u', $term, $matches);
        $words = array_filter($matches[0], fn (string $word) => mb_strlen($word) >= self::FULLTEXT_MIN_WORD);

        $useFullText = $words !== []
            && in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        return $query->where(function (Builder $q) use ($like, $words, $useFullText) {
            // title is a short VARCHAR: a LIKE on it is cheap and also catches
            // partial words the FULLTEXT index can't.
            $q->whereRaw("title like ? escape '!'", [$like]);

            if ($useFullText) {
                $booleanQuery = implode(' ', array_map(fn (string $word) => "+{$word}*", $words));
                $q->orWhereFullText(self::FULLTEXT_COLUMNS, $booleanQuery, ['mode' => 'boolean']);

                return;
            }

            foreach (self::FULLTEXT_COLUMNS as $column) {
                $q->orWhereRaw("{$column} like ? escape '!'", [$like]);
            }
        });
    }

    /**
     * Get the user that owns the meeting.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan unit yang memiliki rapat ini.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(MeetingChatMessage::class)->orderBy('created_at');
    }

    /**
     * Komentar forum tingkat atas (balasan diakses lewat relasi `replies` masing-masing komentar).
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ForumComment::class)->whereNull('parent_id')->orderBy('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('created_at', 'desc');
    }
}
