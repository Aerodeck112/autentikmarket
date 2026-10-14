<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Setări editabile din CRM (stocate în DB, cache-uite).
 * Ex.: setting('integrations.shop_url') — URL-ul Autentik Shop NU este hardcodat.
 */
class Settings
{
    public const CACHE_KEY = 'app.settings.v1';

    /** Valori implicite (placeholder-e unde informația lipsește — nu inventăm date). */
    public const DEFAULTS = [
        'integrations.shop_url' => '',
        'integrations.ai_enabled' => '0',
        'org.name' => '[Denumire organizator — de completat]',
        'org.legal_name' => '[Denumire juridică — de completat]',
        'org.cui' => '[CUI — de completat]',
        'org.trade_register' => '[Nr. Reg. Com. — de completat]',
        'org.address' => '[Adresă sediu — de completat]',
        'org.representative' => '[Reprezentant legal — de completat]',
        'org.email' => '',
        'org.phone' => '',
        'org.iban' => '[IBAN — de completat]',
        'social.facebook' => 'https://www.facebook.com/AutentikMarket/',
        'social.instagram' => '',
        'social.youtube' => '',
        'reservations.hold_minutes' => '15',
        'brand.logo_path' => '',
        'brand.hero_path' => '',
        'mail.reply_to' => '',
        'contracts.number_prefix' => 'AM',
        'seo.default_description_ro' => 'Târg lunar de producători locali și artizani în Târgu Mureș. Produse autentice, direct de la cei care le fac.',
        'seo.default_description_hu' => 'Havi termelői és kézműves vásár Marosvásárhelyen. Autentikus termékek, közvetlenül a készítőktől.',
    ];

    protected static ?array $loaded = null;

    public static function all(): array
    {
        if (static::$loaded !== null) {
            return static::$loaded;
        }

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            $stored = [];
        }

        return static::$loaded = array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        static::flush();
    }

    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v, 'group' => $group]);
        }
        static::flush();
    }

    public static function flush(): void
    {
        static::$loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function holdMinutes(): int
    {
        return max(1, (int) static::get('reservations.hold_minutes', config('autentik.stand_hold_minutes', 15)));
    }

    public static function shopUrl(): ?string
    {
        $url = trim((string) static::get('integrations.shop_url', ''));

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
