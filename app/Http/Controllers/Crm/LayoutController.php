<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\LayoutSlot;
use App\Models\Venue;
use App\Models\VenueLayout;
use App\Services\AuditLogger;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * EDITOR HARTĂ: layout-ul master al locației (nu afectează evenimentele existente — vezi snapshot).
 */
class LayoutController extends Controller
{
    public function store(Request $request, Venue $venue): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'width_m' => ['required', 'numeric', 'min:5', 'max:500'], 'height_m' => ['required', 'numeric', 'min:5', 'max:500']]);
        $layout = $venue->layouts()->create($data + ['is_master' => ! $venue->layouts()->exists()]);

        return redirect()->route('crm.layouts.editor', $layout);
    }

    public function editor(VenueLayout $layout): View
    {
        return view('crm.venues.editor', ['layout' => $layout->load('venue')]);
    }

    public function data(VenueLayout $layout): JsonResponse
    {
        return response()->json($this->payload($layout));
    }

    /** Sincronizare completă: upsert standuri, ștergere cele lipsă, validare coduri unice. */
    public function sync(Request $request, VenueLayout $layout): JsonResponse
    {
        $data = $request->validate([
            'layout.width_m' => ['required', 'numeric', 'min:5', 'max:500'],
            'layout.height_m' => ['required', 'numeric', 'min:5', 'max:500'],
            'layout.background_opacity' => ['required', 'numeric', 'between:0,1'],
            'layout.name' => ['nullable', 'string', 'max:150'],
            'slots' => ['present', 'array', 'max:1000'],
            'slots.*.id' => ['nullable', 'integer'],
            'slots.*.code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/'],
            'slots.*.label' => ['nullable', 'string', 'max:60'],
            'slots.*.x' => ['required', 'numeric', 'between:-50,550'],
            'slots.*.y' => ['required', 'numeric', 'between:-50,550'],
            'slots.*.width' => ['required', 'numeric', 'min:0.3', 'max:100'],
            'slots.*.height' => ['required', 'numeric', 'min:0.3', 'max:100'],
            'slots.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'slots.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'slots.*.has_electricity' => ['boolean'],
            'slots.*.is_blocked' => ['boolean'],
            'slots.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $codes = array_map(fn ($s) => strtoupper(trim($s['code'])), $data['slots']);
        $dupes = array_unique(array_diff_assoc($codes, array_unique($codes)));
        if ($dupes) {
            throw ValidationException::withMessages(['slots' => __('Coduri duplicate: :c', ['c' => implode(', ', $dupes)])]);
        }

        DB::transaction(function () use ($layout, $data) {
            $layout->update([
                'width_m' => $data['layout']['width_m'],
                'height_m' => $data['layout']['height_m'],
                'background_opacity' => $data['layout']['background_opacity'],
                'name' => $data['layout']['name'] ?: $layout->name,
                'version' => $layout->version + 1,
            ]);

            $existing = $layout->slots()->get()->keyBy('id');
            $keep = [];
            // Codurile se eliberează întâi (evită coliziunea UNIQUE la redenumiri încrucișate).
            foreach ($existing as $slot) {
                $slot->update(['code' => '__tmp_'.$slot->id]);
            }
            foreach (array_values($data['slots']) as $i => $s) {
                $attrs = [
                    'code' => strtoupper(trim($s['code'])),
                    'label' => $s['label'] ?? null,
                    'x' => round($s['x'], 2), 'y' => round($s['y'], 2),
                    'width' => round($s['width'], 2), 'height' => round($s['height'], 2),
                    'rotation' => round(fmod($s['rotation'], 360), 2),
                    'price' => $s['price'] ?? null,
                    'has_electricity' => (bool) ($s['has_electricity'] ?? false),
                    'is_blocked' => (bool) ($s['is_blocked'] ?? false),
                    'notes' => $s['notes'] ?? null,
                    'sort_order' => $i + 1,
                ];
                if (! empty($s['id']) && $existing->has($s['id'])) {
                    $existing[$s['id']]->update($attrs);
                    $keep[] = (int) $s['id'];
                } else {
                    $keep[] = LayoutSlot::create($attrs + ['venue_layout_id' => $layout->id])->id;
                }
            }
            $layout->slots()->whereNotIn('id', $keep)->delete();
        });

        AuditLogger::log('layout.saved', $layout, [], ['slots' => count($data['slots']), 'version' => $layout->version]);

        return response()->json($this->payload($layout->fresh()));
    }

    public function background(Request $request, VenueLayout $layout): RedirectResponse
    {
        $request->validate(['background' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('autentik.uploads.image_max_kb')], 'remove' => ['nullable', 'boolean']]);
        if ($layout->background_path && ($request->boolean('remove') || $request->hasFile('background'))) {
            Storage::disk('public')->delete($layout->background_path);
            $layout->background_path = null;
        }
        if ($request->hasFile('background')) {
            $layout->background_path = Uploads::storeImage($request->file('background'), "layouts/{$layout->venue_id}");
        }
        $layout->save();

        return back()->with('success', __('Schița de fundal a fost actualizată.'));
    }

    public function makeMaster(VenueLayout $layout): RedirectResponse
    {
        DB::transaction(function () use ($layout) {
            VenueLayout::where('venue_id', $layout->venue_id)->update(['is_master' => false]);
            $layout->update(['is_master' => true]);
        });
        AuditLogger::log('layout.master_changed', $layout);

        return back()->with('success', __('Layout-ul a devenit MASTER. Evenimentele noi vor folosi acest layout.'));
    }

    public function duplicate(VenueLayout $layout): RedirectResponse
    {
        $copy = DB::transaction(function () use ($layout) {
            $copy = $layout->replicate(['is_master']);
            $copy->name = $layout->name.' (copie)';
            $copy->is_master = false;
            $copy->version = 1;
            $copy->save();
            foreach ($layout->slots as $slot) {
                $s = $slot->replicate();
                $s->venue_layout_id = $copy->id;
                $s->save();
            }

            return $copy;
        });

        return redirect()->route('crm.layouts.editor', $copy)->with('success', __('Layout duplicat.'));
    }

    protected function payload(VenueLayout $layout): array
    {
        return [
            'layout' => [
                'id' => $layout->id, 'name' => $layout->name, 'width_m' => $layout->width_m, 'height_m' => $layout->height_m,
                'background_url' => $layout->backgroundUrl(), 'background_opacity' => $layout->background_opacity, 'version' => $layout->version,
            ],
            'slots' => $layout->slots()->get()->map(fn (LayoutSlot $s) => [
                'id' => $s->id, 'code' => $s->code, 'label' => $s->label, 'x' => $s->x, 'y' => $s->y,
                'width' => $s->width, 'height' => $s->height, 'rotation' => $s->rotation,
                'price' => $s->price !== null ? (float) $s->price : null, 'has_electricity' => $s->has_electricity,
                'is_blocked' => $s->is_blocked, 'notes' => $s->notes,
            ])->values(),
        ];
    }
}
