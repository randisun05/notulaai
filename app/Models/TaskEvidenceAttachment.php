<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEvidenceAttachment extends Model
{
    protected $fillable = [
        'task_evidence_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(TaskEvidence::class, 'task_evidence_id');
    }
}
