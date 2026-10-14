<x-layouts.crm :title="__('Editează').' — '.$producer->brand_name">
    <form method="POST" action="{{ route('crm.producers.update', $producer) }}" class="grid gap-6 lg:grid-cols-3">
        @csrf @method('PUT')
        <div class="card card-pad grid gap-4 sm:grid-cols-2 lg:col-span-2">
            <x-input name="brand_name" :label="__('Denumire brand')" :value="$producer->brand_name" required />
            <x-select name="producer_category_id" :label="__('Categorie')" :options="$categories->pluck('name_ro', 'id')->all()" :value="$producer->producer_category_id" placeholder="—" />
            <x-input name="legal_name" :label="__('Denumire juridică')" :value="$producer->legal_name" />
            <x-input name="cui" label="CUI" :value="$producer->cui" />
            <x-input name="trade_register_no" :label="__('Nr. Registrul Comerțului')" :value="$producer->trade_register_no" />
            <x-input name="representative_name" :label="__('Reprezentant')" :value="$producer->representative_name" />
            <x-input name="address" :label="__('Adresă')" :value="$producer->address" />
            <x-input name="city" :label="__('Localitate')" :value="$producer->city" />
            <x-input name="county" :label="__('Județ')" :value="$producer->county" />
            <x-input name="phone" :label="__('Telefon')" :value="$producer->phone" />
            <x-input name="email" type="email" label="E-mail" :value="$producer->email" />
            <x-input name="website" type="url" label="Website" :value="$producer->website" />
            <x-input name="facebook" type="url" label="Facebook" :value="$producer->facebook" />
            <x-input name="instagram" type="url" label="Instagram" :value="$producer->instagram" />
            <x-input name="iban" label="IBAN" :value="$producer->iban" />
            <x-input name="bank_name" :label="__('Bancă')" :value="$producer->bank_name" />
            <x-textarea name="description_ro" :label="__('Descriere RO')" :value="$producer->description_ro" />
            <x-textarea name="description_hu" :label="__('Descriere HU')" :value="$producer->description_hu" />
            <x-textarea name="story_ro" :label="__('Poveste RO')" :value="$producer->story_ro" />
            <x-textarea name="story_hu" :label="__('Poveste HU')" :value="$producer->story_hu" />
            <x-textarea name="products_ro" :label="__('Produse RO')" :value="$producer->products_ro" />
            <x-textarea name="products_hu" :label="__('Produse HU')" :value="$producer->products_hu" />
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-4">
                <x-select name="preferred_language" :label="__('Limba principală (comunicări automate)')" :options="['ro' => 'Română (RO)', 'hu' => 'Magyar (HU)']" :value="$producer->preferredLanguage()->value" required />
                <x-input name="shop_url" type="url" :label="__('URL pagină în Autentik Shop')" :value="$producer->shop_url" :help="__('Dacă este completat, profilul public afișează „Vezi produsele în Autentik Shop”.')" />
                <x-checkbox name="is_public" :label="__('Profil public vizibil (după aprobare)')" :checked="$producer->is_public" />
                <x-textarea name="internal_notes" :label="__('Note interne (nu sunt vizibile producătorului)')" :value="$producer->internal_notes" rows="4" />
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>
