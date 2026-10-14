<x-layouts.app :title="__('Profil producător')" area="producer">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-3xl sm:text-4xl">{{ __('Profil producător') }}</h1>
        <div class="flex items-center gap-2"><x-status :status="$producer->status" /><x-lang-badge :lang="$producer->preferred_language" /></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('producer.profile.update') }}" class="card card-pad space-y-6 lg:col-span-2">
            @csrf @method('PUT')
            <section>
                <h2 class="text-xl">{{ __('Brand și prezentare') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-input name="brand_name" :label="__('Denumire brand')" :value="$producer->brand_name" required />
                    <x-select name="producer_category_id" :label="__('Categorie')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->icon.' '.$c->t('name')])->all()" :value="$producer->producer_category_id" :placeholder="__('Alege categoria')" />
                    <x-textarea name="description_ro" :label="__('Descriere scurtă (RO)')" :value="$producer->description_ro" rows="3" class="sm:col-span-2" />
                    <x-textarea name="description_hu" :label="__('Descriere scurtă (HU)')" :value="$producer->description_hu" rows="3" class="sm:col-span-2" />
                    <x-textarea name="story_ro" :label="__('Povestea ta (RO)')" :value="$producer->story_ro" rows="5" />
                    <x-textarea name="story_hu" :label="__('Povestea ta (HU)')" :value="$producer->story_hu" rows="5" />
                    <x-textarea name="products_ro" :label="__('Produse prezentate (RO)')" :value="$producer->products_ro" rows="3" />
                    <x-textarea name="products_hu" :label="__('Produse prezentate (HU)')" :value="$producer->products_hu" rows="3" />
                </div>
            </section>
            <section class="border-t border-line pt-6">
                <h2 class="text-xl">{{ __('Date firmă') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-input name="legal_name" :label="__('Denumire juridică')" :value="$producer->legal_name" />
                    <x-input name="cui" :label="__('CUI')" :value="$producer->cui" />
                    <x-input name="trade_register_no" :label="__('Nr. Registrul Comerțului')" :value="$producer->trade_register_no" />
                    <x-input name="representative_name" :label="__('Reprezentant')" :value="$producer->representative_name" />
                    <x-input name="address" :label="__('Adresă')" :value="$producer->address" class="sm:col-span-2" />
                    <x-input name="city" :label="__('Localitate')" :value="$producer->city" />
                    <x-input name="county" :label="__('Județ')" :value="$producer->county" />
                    <x-input name="iban" :label="__('IBAN')" :value="$producer->iban" />
                    <x-input name="bank_name" :label="__('Bancă')" :value="$producer->bank_name" />
                </div>
            </section>
            <section class="border-t border-line pt-6">
                <h2 class="text-xl">{{ __('Contact și social media') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-input name="phone" :label="__('Telefon')" :value="$producer->phone" />
                    <x-input name="email" type="email" :label="__('E-mail')" :value="$producer->email" />
                    <x-input name="website" type="url" :label="__('Website')" :value="$producer->website" placeholder="https://" />
                    <x-input name="facebook" type="url" label="Facebook" :value="$producer->facebook" placeholder="https://facebook.com/…" />
                    <x-input name="instagram" type="url" label="Instagram" :value="$producer->instagram" placeholder="https://instagram.com/…" />
                </div>
            </section>
            <button class="btn-primary btn-lg">{{ __('Salvează profilul') }}</button>
        </form>

        <div class="space-y-6">
            @foreach (['logo' => __('Logo'), 'cover' => __('Imagine copertă')] as $kind => $label)
                <form method="POST" action="{{ route('producer.media.store') }}" enctype="multipart/form-data" class="card card-pad space-y-3">
                    @csrf <input type="hidden" name="kind" value="{{ $kind }}">
                    <h3 class="font-display text-lg">{{ $label }}</h3>
                    @php $url = $kind === 'logo' ? $producer->logoUrl() : $producer->coverUrl(); @endphp
                    @if ($url)<img src="{{ $url }}" alt="" class="{{ $kind === 'logo' ? 'size-24 rounded-full' : 'aspect-video w-full rounded-xl' }} object-cover">@endif
                    <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm">
                    <button class="btn-ghost btn-sm">{{ __('Încarcă') }}</button>
                </form>
            @endforeach
            <div class="card card-pad">
                <h3 class="font-display text-lg">{{ __('Galerie') }} ({{ $producer->gallery->count() }}/12)</h3>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($producer->gallery as $m)
                        <form method="POST" action="{{ route('producer.media.destroy', $m) }}" class="group relative">
                            @csrf @method('DELETE')
                            <img src="{{ $m->url() }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                            <button class="absolute right-1 top-1 hidden rounded-full bg-white px-2 text-xs text-red-700 shadow group-hover:block" aria-label="{{ __('Șterge') }}">✕</button>
                        </form>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('producer.media.store') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                    @csrf <input type="hidden" name="kind" value="gallery">
                    <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm">
                    <button class="btn-ghost btn-sm">{{ __('Adaugă fotografie') }}</button>
                </form>
            </div>
            <p class="text-xs text-ink-soft">{{ __('Limba de comunicare se modifică de echipa Autentik Market la cerere.') }}</p>
        </div>
    </div>
</x-layouts.app>
