<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => NotificationStatus::class,
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
