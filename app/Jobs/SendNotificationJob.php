<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Mail\TemplatedMail;
use App\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $logId) {}

    public function handle(): void
    {
        $log = NotificationLog::find($this->logId);
        if (! $log || in_array($log->status, [NotificationStatus::Sent, NotificationStatus::Skipped], true)) {
            return;
        }

        $log->forceFill(['status' => NotificationStatus::Processing, 'attempts' => $log->attempts + 1])->save();

        try {
            Mail::to($log->recipient_email)->send(new TemplatedMail($log->subject, $log->body, $log->language));
            $log->forceFill(['status' => NotificationStatus::Sent, 'sent_at' => now(), 'error' => null])->save();
        } catch (Throwable $e) {
            $log->forceFill(['status' => NotificationStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();
            throw $e;
        }
    }
}
