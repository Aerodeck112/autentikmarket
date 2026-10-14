<x-layouts.app :title="__('Recenzie')">
    <div class="mx-auto max-w-xl">
        <p class="eyebrow">{{ $interaction->event->t('title') }} · {{ format_date_local($interaction->event->event_date) }}</p>
        <h1 class="mt-2 text-3xl">{{ __('Cum ți s-a părut :name?', ['name' => $interaction->producer->brand_name]) }}</h1>
        <form method="POST" action="{{ URL::temporarySignedRoute('reviews.store', now()->addHours(2), ['interaction' => $interaction->id]) }}" enctype="multipart/form-data" class="card card-pad mt-6 space-y-5" x-data="{ rating: {{ (int) old('rating', 5) }} }">
            @csrf
            <div>
                <p class="field-label">{{ __('Nota ta') }} *</p>
                <div class="flex gap-1 text-4xl">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" @click="rating = {{ $i }}" :class="rating >= {{ $i }} ? 'text-honey' : 'text-line'" aria-label="{{ $i }}">★</button>
                    @endfor
                </div>
                <input type="hidden" name="rating" :value="rating">
            </div>
            <x-textarea name="body" :label="__('Câteva cuvinte (opțional)')" rows="4" />
            <x-input name="product_name" :label="__('Ce ai cumpărat? (opțional)')" />
            <x-input name="photo" type="file" :label="__('Fotografie (opțional)')" accept="image/jpeg,image/png,image/webp" />
            <p class="text-xs text-ink-soft">{{ __('Recenzia va fi publicată după moderare, cu prenumele și inițiala numelui.') }}</p>
            <button class="btn-primary btn-lg w-full">{{ __('Trimite recenzia') }}</button>
        </form>
    </div>
</x-layouts.app>
