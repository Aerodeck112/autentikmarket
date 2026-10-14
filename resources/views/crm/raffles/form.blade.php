<x-layouts.crm :title="$raffle->exists ? __('Editează tombola') : __('Tombolă nouă')">
    <form method="POST" action="{{ $raffle->exists ? route('crm.raffles.update', $raffle) : route('crm.raffles.store') }}" class="grid gap-6 lg:grid-cols-3"
          x-data="{ prizes: @js(old('prizes', $raffle->exists ? $raffle->prizes->map(fn ($p) => ['name_ro' => $p->name_ro, 'name_hu' => $p->name_hu, 'quantity' => $p->quantity])->values() : [['name_ro' => '', 'name_hu' => '', 'quantity' => 1]])) }">
        @csrf @if ($raffle->exists) @method('PUT') @endif
        <div class="card card-pad grid gap-4 sm:grid-cols-2 lg:col-span-2">
            <x-select name="event_id" :label="__('Eveniment')" :options="$events->mapWithKeys(fn ($e) => [$e->id => $e->title_ro.' · '.$e->event_date->format('d.m.Y')])->all()" :value="$raffle->event_id" class="sm:col-span-2" required />
            <x-input name="name_ro" :label="__('Nume RO')" :value="$raffle->name_ro" required />
            <x-input name="name_hu" :label="__('Nume HU')" :value="$raffle->name_hu" required />
            <x-textarea name="description_ro" :label="__('Descriere RO')" :value="$raffle->description_ro" rows="3" />
            <x-textarea name="description_hu" :label="__('Descriere HU')" :value="$raffle->description_hu" rows="3" />
            <x-textarea name="conditions_ro" :label="__('Condiții RO')" :value="$raffle->conditions_ro" rows="3" />
            <x-textarea name="conditions_hu" :label="__('Condiții HU')" :value="$raffle->conditions_hu" rows="3" />
            <div class="sm:col-span-2">
                <p class="field-label">{{ __('Premii') }}</p>
                <template x-for="(p, i) in prizes" :key="i">
                    <div class="mb-2 grid grid-cols-[1fr_1fr_80px_auto] gap-2">
                        <input class="field-input" :name="`prizes[${i}][name_ro]`" x-model="p.name_ro" placeholder="RO">
                        <input class="field-input" :name="`prizes[${i}][name_hu]`" x-model="p.name_hu" placeholder="HU">
                        <input class="field-input" type="number" min="1" :name="`prizes[${i}][quantity]`" x-model="p.quantity">
                        <button type="button" class="btn-ghost btn-sm" @click="prizes.splice(i, 1)">✕</button>
                    </div>
                </template>
                <button type="button" class="btn-ghost btn-sm" @click="prizes.push({ name_ro: '', name_hu: '', quantity: 1 })">+ {{ __('Premiu') }}</button>
            </div>
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-4">
                <x-input name="winners_count" type="number" :label="__('Număr câștigători')" :value="$raffle->winners_count" required />
                <x-input name="entry_start" type="datetime-local" :label="__('Înscrieri de la')" :value="$raffle->entry_start?->format('Y-m-d\TH:i')" />
                <x-input name="entry_end" type="datetime-local" :label="__('Înscrieri până la')" :value="$raffle->entry_end?->format('Y-m-d\TH:i')" />
                <x-input name="draw_at" type="datetime-local" :label="__('Data extragerii')" :value="$raffle->draw_at?->format('Y-m-d\TH:i')" />
                <x-input name="min_visits" type="number" :label="__('Minim vizite (total)')" :value="$raffle->min_visits" />
                <x-checkbox name="requires_checkin" :label="__('Eligibil doar cu check-in la acest eveniment')" :checked="$raffle->requires_checkin" />
                <x-checkbox name="auto_enroll_on_checkin" :label="__('Înscriere automată la check-in')" :checked="$raffle->auto_enroll_on_checkin" />
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>
