<?php

namespace App\Models;

use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumComment extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'parent_id',
        'body',
    ];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** Comments on meetings the user may see whose body contains the term. */
    public function scopeVisibleSearch(Builder $query, User $user, string $term): Builder
    {
        return $query
            ->whereHas('meeting', fn (Builder $q) => $q->visibleTo($user))
            ->whereRaw("forum_comments.body like ? escape '!'", [Like::contains(trim($term))]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ForumCommentAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ForumCommentReaction::class);
    }

    public function mentionedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forum_comment_mentions')->withTimestamps();
    }
}
