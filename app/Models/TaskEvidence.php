<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskEvidence extends Model
{
    // "evidence" adalah uncountable noun — konvensi Eloquent akan infer
    // "task_evidence" (tanpa s), padahal migration pakai "task_evidences".
    protected $table = 'task_evidences';

    protected $fillable = [
        'task_id',
        'user_id',
        'note',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskEvidenceAttachment::class);
    }
}
