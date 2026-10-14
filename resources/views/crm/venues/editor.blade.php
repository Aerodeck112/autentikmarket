<x-layouts.crm :title="__('Editor hartă').' — '.$layout->venue->name">
    <x-slot:breadcrumb><a href="{{ route('crm.venues.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Hartă & standuri') }}</a></x-slot:breadcrumb>
    <x-slot:actions>
        @unless ($layout->is_master)<form method="POST" action="{{ route('crm.layouts.master', $layout) }}">@csrf<button class="btn-ghost">{{ __('Setează ca MASTER') }}</button></form>@endunless
        <form method="POST" action="{{ route('crm.layouts.duplicate', $layout) }}">@csrf<button class="btn-ghost">{{ __('Duplică layout') }}</button></form>
    </x-slot:actions>

    <div x-data="mapEditor({
            dataUrl: '{{ route('crm.layouts.data', $layout) }}',
            saveUrl: '{{ route('crm.layouts.sync', $layout) }}',
            prefix: '{{ $layout->venue->code }}',
            labels: { saved: @js(__('Harta a fost salvată.')), saveError: @js(__('Salvarea a eșuat.')), confirmRenumber: @js(__('Renumerotezi toate standurile în ordine (rânduri, de la stânga la dreapta)?')) }
         })" class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="card overflow-hidden">
            {{-- toolbar --}}
            <div class="flex flex-wrap items-center gap-1.5 border-b border-line bg-cream/50 p-2 text-sm">
                <button type="button" class="btn-primary btn-sm" @click="add()">+ {{ __('Stand') }}</button>
                <button type="button" class="btn-ghost btn-sm" @click="duplicate()" :disabled="!selected" title="Ctrl+D">{{ __('Duplică') }}</button>
                <button type="button" class="btn-ghost btn-sm" @click="rotateBy(90)" :disabled="!selected" title="R">⟳ 90°</button>
                <button type="button" class="btn-ghost btn-sm" @click="remove()" :disabled="!selected" title="Delete">🗑 {{ __('Șterge') }}</button>
                <button type="button" class="btn-ghost btn-sm" @click="undo()" :disabled="!history.length" title="Ctrl+Z">↶ {{ __('Anulează') }}</button>
                <button type="button" class="btn-ghost btn-sm" @click="renumber()">#️⃣ {{ __('Numerotează') }}</button>
                <span class="mx-1 h-5 w-px bg-line"></span>
                <label class="flex items-center gap-1 text-xs">{{ __('Grilă') }}
                    <select x-model.number="snap" class="rounded-lg border-line py-1 text-xs"><option value="0">—</option><option value="0.1">0,1 m</option><option value="0.25">0,25 m</option><option value="0.5">0,5 m</option><option value="1">1 m</option></select>
                </label>
                <span class="mx-1 h-5 w-px bg-line"></span>
                <button type="button" class="btn-ghost btn-sm !px-2.5" @click="zoomIn()">＋</button>
                <button type="button" class="btn-ghost btn-sm !px-2.5" @click="zoomOut()">−</button>
                <button type="button" class="btn-ghost btn-sm !px-2.5" @click="fit()">⤢</button>
                <div class="ml-auto flex items-center gap-2">
                    <span x-show="dirty" class="text-xs text-amber-700">● {{ __('modificări nesalvate') }}</span>
                    <button type="button" class="btn-accent btn-sm" @click="save()" :disabled="saving">{{ __('Salvează harta') }}</button>
                </div>
            </div>
            <p x-show="message" x-text="message" class="bg-emerald-50 px-3 py-2 text-sm text-emerald-800"></p>
            <p x-show="error" x-text="error" class="bg-red-50 px-3 py-2 text-sm text-red-800"></p>
            <svg x-ref="svg" class="block h-[70vh] w-full touch-none select-none bg-[#f6f1e2]" preserveAspectRatio="xMidYMid meet"><g x-ref="layer"></g></svg>
            <p class="border-t border-line px-3 py-2 text-xs text-ink-soft">{{ __('Trage standurile pentru a le muta · mânerul portocaliu = redimensionare · cercul alb = rotire (Alt = liber) · săgeți = 10 cm (Shift = 1 m) · rotița/pinch = zoom · trage fundalul = pan') }}</p>
        </div>

        <div class="space-y-4">
            <div class="card card-pad space-y-3 text-sm">
                <h3 class="font-display text-lg">{{ __('Stand selectat') }}</h3>
                <template x-if="!selected"><p class="text-ink-soft">{{ __('Click pe un stand pentru a-l edita.') }}</p></template>
                <template x-if="selected">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="col-span-2"><span class="field-label">{{ __('Cod') }}</span><input class="field-input" x-model="selected.code" @input="updateField()"></label>
                        <label class="col-span-2"><span class="field-label">{{ __('Etichetă') }}</span><input class="field-input" x-model="selected.label" @input="updateField()"></label>
                        <label><span class="field-label">X (m)</span><input type="number" step="0.1" class="field-input" x-model.number="selected.x" @input="updateField()"></label>
                        <label><span class="field-label">Y (m)</span><input type="number" step="0.1" class="field-input" x-model.number="selected.y" @input="updateField()"></label>
                        <label><span class="field-label">{{ __('Lățime (m)') }}</span><input type="number" step="0.1" min="0.3" class="field-input" x-model.number="selected.width" @input="updateField()"></label>
                        <label><span class="field-label">{{ __('Adâncime (m)') }}</span><input type="number" step="0.1" min="0.3" class="field-input" x-model.number="selected.height" @input="updateField()"></label>
                        <label><span class="field-label">{{ __('Rotație (°)') }}</span><input type="number" step="1" class="field-input" x-model.number="selected.rotation" @input="updateField()"></label>
                        <label><span class="field-label">{{ __('Preț (lei)') }}</span><input type="number" step="0.01" min="0" class="field-input" x-model="selected.price" @input="updateField()" placeholder="{{ __('la cerere') }}"></label>
                        <label class="col-span-2 flex items-center gap-2"><input type="checkbox" class="rounded border-line text-forest" x-model="selected.has_electricity" @change="updateField()"> ⚡ {{ __('Electricitate') }}</label>
                        <label class="col-span-2 flex items-center gap-2"><input type="checkbox" class="rounded border-line text-forest" x-model="selected.is_blocked" @change="updateField()"> 🚫 {{ __('Spațiu blocat') }}</label>
                        <label class="col-span-2"><span class="field-label">{{ __('Note / caracteristici') }}</span><input class="field-input" x-model="selected.notes" @input="updateField()"></label>
                    </div>
                </template>
            </div>

            <div class="card card-pad space-y-3 text-sm">
                <h3 class="font-display text-lg">{{ __('Layout') }}</h3>
                <label><span class="field-label">{{ __('Nume') }}</span><input class="field-input" x-model="layout.name" @input="dirty = true"></label>
                <div class="grid grid-cols-2 gap-2">
                    <label><span class="field-label">{{ __('Lățime teren (m)') }}</span><input type="number" step="0.5" class="field-input" x-model.number="layout.width_m" @input="dirty = true; render()"></label>
                    <label><span class="field-label">{{ __('Adâncime teren (m)') }}</span><input type="number" step="0.5" class="field-input" x-model.number="layout.height_m" @input="dirty = true; render()"></label>
                </div>
                <label><span class="field-label">{{ __('Opacitate schiță') }}</span><input type="range" min="0" max="1" step="0.05" class="w-full" x-model.number="layout.background_opacity" @input="dirty = true; render()"></label>
                <p class="text-xs text-ink-soft" x-text="slots.length + ' {{ __('standuri') }}'"></p>
            </div>

            <form method="POST" action="{{ route('crm.layouts.background', $layout) }}" enctype="multipart/form-data" class="card card-pad space-y-2 text-sm">@csrf
                <h3 class="font-display text-lg">{{ __('Schiță de fundal') }}</h3>
                <p class="text-xs text-ink-soft">{{ __('Încarcă schița (JPG/PNG/WebP; pentru PDF exportă întâi pagina ca imagine). Setează dimensiunile reale ale terenului pentru calibrare, apoi desenează standurile peste schiță.') }}</p>
                <input type="file" name="background" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs">
                <div class="flex gap-2"><button class="btn-ghost btn-sm">{{ __('Încarcă') }}</button>
                    @if ($layout->background_path)<button name="remove" value="1" class="btn-ghost btn-sm">{{ __('Elimină') }}</button>@endif</div>
                <p class="text-xs text-amber-700">{{ __('Salvează harta înainte de a încărca schița.') }}</p>
            </form>
        </div>
    </div>
</x-layouts.crm>
