<?php

namespace App\Services\Events;

use App\Enums\SlotStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\EventLayout;
use App\Models\StandReservation;
use App\Models\VenueLayout;
use Illuminate\Support\Facades\DB;

/**
 * Versionarea hărții: la crearea evenimentului se copiază layout-ul MASTER al locației.
 * Modificările ulterioare ale master-ului NU afectează evenimentele existente.
 */
class LayoutSnapshotService
{
    public function snapshot(Event $event, VenueLayout $layout, bool $force = false): EventLayout
    {
        return DB::transaction(function () use ($event, $layout, $force) {
            $existing = $event->layout()->first();
            if ($existing) {
                if (StandReservation::where('event_id', $event->id)->exists() && ! $force) {
                    throw new BusinessRuleException(__('Evenimentul are deja rezervări; harta nu poate fi resincronizată.'), 'has_reservations');
                }
                $existing->slots()->delete();
                $existing->delete();
            }

            $eventLayout = EventLayout::create([
                'event_id' => $event->id,
                'source_layout_id' => $layout->id,
                'source_version' => $layout->version,
                'width_m' => $layout->width_m,
                'height_m' => $layout->height_m,
                'background_path' => $layout->background_path,
                'background_opacity' => $layout->background_opacity,
                'elements' => $layout->elements,
            ]);

            $now = now();
            $rows = $layout->slots()->get()->map(fn ($s) => [
                'event_layout_id' => $eventLayout->id,
                'event_id' => $event->id,
                'source_slot_id' => $s->id,
                'code' => $s->code,
                'label' => $s->label,
                'x' => $s->x,
                'y' => $s->y,
                'width' => $s->width,
                'height' => $s->height,
                'rotation' => $s->rotation,
                'price' => $s->price,
                'has_electricity' => $s->has_electricity,
                'features' => $s->features ? json_encode($s->features) : null,
                'status' => $s->is_blocked ? SlotStatus::Blocked->value : SlotStatus::Available->value,
                'notes' => $s->notes,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('event_slots')->insert($chunk);
            }

            return $eventLayout;
        });
    }
}
