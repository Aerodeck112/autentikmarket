<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificări interne către conturile de Administrator și Super Admin
 * (producător nou înscris, rezervare temporară de stand, cerere de stand), fiecare în limba lui.
 */
class AdminNotifier
{
    public function __construct(protected NotificationService $notifications) {}

    /** @return Collection<int, User> */
    public function recipients(): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->where('is_active', true)->whereNull('anonymized_at')->whereNotNull('email')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{ro: string, hu: string}  $actionLabel
     */
    public function notify(string $key, array $data, Model $related, string $dedupe, string $actionUrl, array $actionLabel): int
    {
        $sent = 0;
        foreach ($this->recipients() as $admin) {
            $log = $this->notifications->send($key, $admin, $data, [
                'related' => $related,
                'dedupe_key' => "{$dedupe}:{$admin->id}",
                'action_url' => $actionUrl,
                'action_label_ro' => $actionLabel['ro'],
                'action_label_hu' => $actionLabel['hu'],
            ]);
            $sent += $log ? 1 : 0;
        }

        return $sent;
    }
}
