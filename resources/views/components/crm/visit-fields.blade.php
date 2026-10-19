{{-- Ediția la care a fost cumpărătorul + producătorii de la care a cumpărat (înregistrare din CRM). --}}
@props(['events', 'producers', 'participants', 'required' => false])
<div class="space-y-3" x-data="{ ev: @js((string) old('event_id', '')), q: '', part: @js($participants), isPart(id) { return (this.part[this.ev] || []).includes(id) } }">
    <div>
        <label class="field-label" for="event_id">{{ __('Ediția târgului') }} @if ($required)<span class="text-clay">*</span>@endif</label>
        <select id="event_id" name="event_id" x-model="ev" class="field-input" @required($required)>
            <option value="">{{ $required ? __('Alege ediția…') : __('— fără vizită —') }}</option>
            @foreach ($events as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
        </select>
        @error('event_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div x-show="ev" x-cloak>
        <div class="flex items-end justify-between gap-2">
            <span class="field-label">{{ __('A cumpărat de la') }}</span>
            <input type="search" x-model="q" placeholder="{{ __('Caută producător…') }}" class="field-input !w-44 !py-1 text-xs">
        </div>
        <div class="mt-1 max-h-56 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
            @forelse ($producers as $id => $name)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-cream"
                       x-show="!q || @js(mb_strtolower($name)).includes(q.toLowerCase())">
                    <input type="checkbox" name="producer_ids[]" value="{{ $id }}" class="rounded border-line text-forest" @checked(in_array($id, (array) old('producer_ids', [])))>
                    <span>{{ $name }}</span>
                    <span x-show="isPart({{ $id }})" class="ml-auto rounded-full bg-emerald-50 px-2 text-[10px] font-semibold text-emerald-800">{{ __('la această ediție') }}</span>
                </label>
            @empty
                <p class="text-xs text-ink-soft">{{ __('Niciun producător aprobat încă.') }}</p>
            @endforelse
        </div>
        <p class="mt-1 text-xs text-ink-soft">{{ __('Vizita se adaugă la numărul de vizite (cadouri de fidelitate). Cumpărăturile apar în fișa producătorului și dau dreptul la recenzie.') }}</p>
    </div>
</div>
