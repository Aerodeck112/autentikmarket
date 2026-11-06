<x-layouts.crm :title="$event->exists ? __('Editează evenimentul') : __('Eveniment nou')">
    <form method="POST" action="{{ $event->exists ? route('crm.events.update', $event) : route('crm.events.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3"
          x-data="{ date: @js((string) old('event_date', $event->event_date?->toDateString())), today: @js(today()->toDateString()), get past() { return this.date !== '' && this.date < this.today } }">
        @csrf @if ($event->exists) @method('PUT') @endif
        @unless ($event->exists)
            <div x-show="past" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 lg:col-span-3">
                <p class="font-semibold">{{ __('Ediție trecută (adăugată retroactiv)') }}</p>
                <p class="mt-1">{{ __('Data este în trecut: ediția se salvează direct ca „Încheiat”, în arhivă. Nu se trimit e-mailuri (nici mulțumiri, nici cereri de recenzie) și nu se generează task-uri. Scrie numărul real al ediției și bifează producătorii prezenți; vizitele cumpărătorilor le înregistrezi apoi din fișa fiecăruia (Cumpărători → + Înregistrează vizită la târg).') }}</p>
            </div>
            @if ($past)
                <p x-show="!past" class="rounded-2xl border border-line bg-white p-4 text-sm lg:col-span-3">{{ __('Alege data ediției trecute (o zi dinainte de azi).') }}</p>
            @endif
        @endunless
        <div class="card card-pad space-y-4 lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="title_ro" :label="__('Titlu RO')" :value="$event->title_ro" required />
                <x-input name="title_hu" :label="__('Titlu HU')" :value="$event->title_hu" required />
                <x-textarea name="description_ro" :label="__('Descriere RO')" :value="$event->description_ro" rows="6" />
                <x-textarea name="description_hu" :label="__('Descriere HU')" :value="$event->description_hu" rows="6" />
                <x-textarea name="program_ro" :label="__('Program RO')" :value="$event->program_ro" rows="3" />
                <x-textarea name="program_hu" :label="__('Program HU')" :value="$event->program_hu" rows="3" />
            </div>
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-4">
                <x-select name="venue_id" :label="__('Locație')" :options="$venues->pluck('name', 'id')->all()" :value="$event->venue_id" :placeholder="__('— fără locație (fără hartă) —')" :help="$event->exists ? null : __('Layout-ul MASTER al locației va fi copiat automat (snapshot).')" />
                <x-input name="city" :label="__('Oraș')" :value="$event->city" required />
                <x-input name="venue_name" :label="__('Denumire loc')" :value="$event->venue_name" />
                <x-input name="address" :label="__('Adresă')" :value="$event->address" />
                <x-input name="event_date" type="date" :label="__('Data')" :value="$event->event_date?->toDateString()" required x-model="date" />
                <x-input name="edition" type="number" min="1" :label="__('Ediția nr.')" :value="$event->edition" :help="__('Gol = următorul număr pentru această locație. Obligatoriu la edițiile trecute. Corectați dacă târgul a avut ediții înainte de platformă.')" />
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="start_time" type="time" :label="__('Ora început')" :value="$event->start_time ? substr($event->start_time, 0, 5) : null" />
                    <x-input name="end_time" type="time" :label="__('Ora sfârșit')" :value="$event->end_time ? substr($event->end_time, 0, 5) : null" />
                </div>
                <div x-show="!past" class="space-y-4">
                    <x-input name="registration_start" type="datetime-local" :label="__('Înscrieri de la')" :value="$event->registration_start?->format('Y-m-d\TH:i')" />
                    <x-input name="registration_end" type="datetime-local" :label="__('Înscrieri până la')" :value="$event->registration_end?->format('Y-m-d\TH:i')" />
                </div>
                <x-input name="max_producers" type="number" :label="__('Număr maxim producători')" :value="$event->max_producers" />
                <x-input name="cover" type="file" :label="__('Imagine copertă')" accept="image/*" />
            </div>
            {{-- Producătorii prezenți la o ediție trecută (doar pentru date din trecut). --}}
            <div x-show="past" x-cloak class="card card-pad space-y-2" x-data="{ q: '' }">
                <input type="hidden" name="present_form" value="1" :disabled="!past">
                <div class="flex items-end justify-between gap-2">
                    <h2 class="text-lg">{{ __('Producători prezenți') }}</h2>
                    <input type="search" x-model="q" placeholder="{{ __('Caută producător…') }}" class="field-input !w-40 !py-1 text-xs">
                </div>
                <div class="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
                    @forelse ($producers as $id => $name)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-cream" x-show="!q || @js(mb_strtolower($name)).includes(q.toLowerCase())">
                            <input type="checkbox" name="present_ids[]" value="{{ $id }}" :disabled="!past" class="rounded border-line text-forest" @checked(in_array($id, array_map('intval', (array) old('present_ids', $present))))>
                            <span>{{ $name }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-ink-soft">{{ __('Niciun producător în CRM încă. Adaugă-i din Producători → + Producător nou.') }}</p>
                    @endforelse
                </div>
                <p class="text-xs text-ink-soft">{{ __('Apar în pagina ediției și sunt evidențiați când înregistrezi cumpărăturile unui cumpărător la această ediție.') }}</p>
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>
