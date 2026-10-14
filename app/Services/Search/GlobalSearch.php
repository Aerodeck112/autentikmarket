<?php

namespace App\Services\Search;

use App\Models\Contract;
use App\Models\Event;
use App\Models\Producer;
use App\Models\StandReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Căutare globală în CRM — MySQL LIKE indexat (fără Elasticsearch).
 */
class GlobalSearch
{
    public function search(string $term, User $actor, int $limit = 8): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $results = [];

        if ($actor->hasPermission('visitors.view')) {
            $results['visitors'] = User::query()
                ->where(fn ($q) => $q->where('users.first_name', 'like', $like)->orWhere('users.last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('member_code', 'like', $like)
                    ->orWhereRaw(self::fullNameSql().' like ?', [$like]))
                ->limit($limit)->get();
        }
        if ($actor->hasPermission('producers.view')) {
            $results['producers'] = Producer::query()
                ->where(fn ($q) => $q->where('brand_name', 'like', $like)->orWhere('legal_name', 'like', $like)
                    ->orWhere('representative_name', 'like', $like)->orWhere('cui', 'like', $like)->orWhere('email', 'like', $like))
                ->limit($limit)->get();
        }
        if ($actor->hasPermission('contracts.manage')) {
            $results['contracts'] = Contract::with('producer')
                ->where(fn ($q) => $q->where('number', 'like', $like)
                    ->orWhereHas('producer', fn ($p) => $p->where('brand_name', 'like', $like)->orWhere('legal_name', 'like', $like)))
                ->limit($limit)->get();
        }
        if ($actor->hasPermission('events.view')) {
            $results['events'] = Event::query()
                ->where(fn ($q) => $q->where('title_ro', 'like', $like)->orWhere('title_hu', 'like', $like)->orWhere('city', 'like', $like))
                ->orderByDesc('event_date')->limit($limit)->get();
        }
        if ($actor->hasPermission('reservations.manage')) {
            $results['reservations'] = StandReservation::with(['producer', 'event', 'slot'])
                ->where(fn ($q) => $q->whereHas('producer', fn ($p) => $p->where('brand_name', 'like', $like))
                    ->orWhereHas('slot', fn ($s) => $s->where('code', 'like', $like)))
                ->latest('id')->limit($limit)->get();
        }

        return array_filter($results, fn ($c) => $c->isNotEmpty());
    }

    /** Concatenare portabilă (MySQL/MariaDB: CONCAT; SQLite: ||). */
    public static function fullNameSql(string $table = 'users'): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "({$table}.first_name || ' ' || {$table}.last_name)"
            : "CONCAT({$table}.first_name, ' ', {$table}.last_name)";
    }
}
