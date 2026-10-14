<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Jurnal de audit pentru acțiunile importante (aprobări, rezervări, extrageri, update-uri…).
 */
class AuditLogger
{
    /** Câmpuri care nu ajung niciodată în audit. */
    protected const REDACT = ['password', 'remember_token', 'card_token', 'qr_token', 'iban'];

    public static function log(string $action, ?Model $entity = null, array $old = [], array $new = [], ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : Request::instance();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $entity ? $entity->getMorphClass() : null,
            'auditable_id' => $entity?->getKey(),
            'old_values' => static::clean($old) ?: null,
            'new_values' => static::clean($new) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    protected static function clean(array $values): array
    {
        foreach (self::REDACT as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '***';
            }
        }

        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $values);
    }
}
