<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    use ScopedToUnit;

    public const EVENTS = [
        'task.created',
        'task.status_changed',
        'task.approved',
        'meeting.processed',
    ];

    protected $fillable = [
        'unit_id',
        'created_by',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSubscribedTo(Builder $query, string $event): Builder
    {
        return $query->whereJsonContains('events', $event);
    }
}
