<?php

namespace App\Services\Accounts;

use App\Models\Producer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\URL;

/**
 * Invitații pentru conturile create de organizator în CRM (cumpărători, producători).
 *
 * Linkul este un URL semnat (APP_KEY), valabil 7 zile, care conține o amprentă a parolei curente:
 * după ce persoana își alege parola (sau o resetează), amprenta se schimbă și linkul nu mai funcționează.
 * Nu se păstrează niciun token în baza de date.
 */
class InvitationService
{
    public const VALID_DAYS = 7;

    public function __construct(protected NotificationService $notifications) {}

    public function link(User $user): string
    {
        return URL::temporarySignedRoute('invite.show', now()->addDays(self::VALID_DAYS), [
            'user' => $user->id,
            'h' => $this->fingerprint($user),
        ]);
    }

    public function fingerprint(User $user): string
    {
        return substr(hash('sha256', (string) $user->password), 0, 20);
    }

    public function isValidFor(User $user, string $fingerprint): bool
    {
        return $user->is_active && ! $user->anonymized_at && hash_equals($this->fingerprint($user), $fingerprint);
    }

    /** Trimite invitația în limba destinatarului. Fără e-mail → nu se trimite nimic (false). */
    public function send(User $user, ?Producer $producer = null): bool
    {
        if (! $user->email) {
            return false;
        }
        $url = $this->link($user);
        $key = $producer ? 'PRODUCER_INVITE' : 'ACCOUNT_INVITE';
        $log = $this->notifications->send($key, $user, [
            'member_code' => $user->member_code,
            'producer_name' => $producer?->brand_name,
        ], [
            'related' => $producer ?? $user,
            'dedupe_key' => 'invite:'.$user->id.':'.now()->format('YmdHis'),
            'action_url' => $url,
            'action_label_ro' => 'Alege parola',
            'action_label_hu' => 'Jelszó megadása',
        ]);
        AuditLogger::log('account.invited', $user, [], ['type' => $producer ? 'producer' : 'buyer']);

        return (bool) $log;
    }
}
