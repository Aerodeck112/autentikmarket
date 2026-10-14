<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
        'relative_days' => 'integer',
        'priority' => TaskPriority::class,
    ];
}
