<x-layouts.crm :title="$venue->exists ? __('Editează locația') : __('Locație nouă')">
    <form method="POST" action="{{ $venue->exists ? route('crm.venues.update', $venue) : route('crm.venues.store') }}" class="card card-pad grid max-w-4xl gap-4 sm:grid-cols-2">
        @csrf @if ($venue->exists) @method('PUT') @endif
        <x-input name="name" :label="__('Denumire')" :value="$venue->name" required />
        <x-input name="code" :label="__('Cod (prefix standuri, ex. TM)')" :value="$venue->code" required />
        <x-input name="city" :label="__('Oraș (RO)')" :value="$venue->city" required />
        <x-input name="city_hu" :label="__('Oraș (HU)')" :value="$venue->city_hu" />
        <x-input name="address" :label="__('Adresă (RO)')" :value="$venue->address" />
        <x-input name="address_hu" :label="__('Adresă (HU)')" :value="$venue->address_hu" />
        <x-input name="county" :label="__('Județ')" :value="$venue->county" />
        <div class="grid grid-cols-2 gap-3"><x-input name="lat" :label="__('Latitudine')" :value="$venue->lat" /><x-input name="lng" :label="__('Longitudine')" :value="$venue->lng" /></div>
        <x-textarea name="description_ro" :label="__('Descriere RO')" :value="$venue->description_ro" />
        <x-textarea name="description_hu" :label="__('Descriere HU')" :value="$venue->description_hu" />
        <x-checkbox name="active" :label="__('Activă')" :checked="$venue->active" />
        <div class="sm:col-span-2"><button class="btn-primary">{{ __('Salvează') }}</button></div>
    </form>
</x-layouts.crm>
