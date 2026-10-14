<x-layouts.crm :title="$event->exists ? __('Editează evenimentul') : __('Eveniment nou')">
    <form method="POST" action="{{ $event->exists ? route('crm.events.update', $event) : route('crm.events.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf @if ($event->exists) @method('PUT') @endif
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
                <x-input name="event_date" type="date" :label="__('Data')" :value="$event->event_date?->toDateString()" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="start_time" type="time" :label="__('Ora început')" :value="$event->start_time ? substr($event->start_time, 0, 5) : null" />
                    <x-input name="end_time" type="time" :label="__('Ora sfârșit')" :value="$event->end_time ? substr($event->end_time, 0, 5) : null" />
                </div>
                <x-input name="registration_start" type="datetime-local" :label="__('Înscrieri de la')" :value="$event->registration_start?->format('Y-m-d\TH:i')" />
                <x-input name="registration_end" type="datetime-local" :label="__('Înscrieri până la')" :value="$event->registration_end?->format('Y-m-d\TH:i')" />
                <x-input name="max_producers" type="number" :label="__('Număr maxim producători')" :value="$event->max_producers" />
                <x-input name="cover" type="file" :label="__('Imagine copertă')" accept="image/*" />
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>
