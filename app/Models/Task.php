<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value]);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereDate('due_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [TaskStatus::Open, TaskStatus::InProgress], true)
            && $this->due_date && $this->due_date->lt(now()->startOfDay());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
