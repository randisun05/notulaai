<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumCommentAttachment extends Model
{
    protected $fillable = [
        'forum_comment_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ForumComment::class, 'forum_comment_id');
    }
}
