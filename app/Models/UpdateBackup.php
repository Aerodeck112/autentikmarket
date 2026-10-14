<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpdateBackup extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'database_included' => 'boolean',
        'new_files' => 'array',
    ];

    public function applicationUpdate(): BelongsTo
    {
        return $this->belongsTo(ApplicationUpdate::class, 'application_update_id');
    }
}
