{{-- Categoriile unui producător (mai multe). Prima bifată devine principală dacă cea principală nu mai e bifată. --}}
@props(['categories', 'selected' => [], 'primary' => null])
@php $chosen = array_map('intval', (array) old('category_ids', $selected)); @endphp
<div {{ $attributes->only('class') }}>
    <input type="hidden" name="categories_form" value="1">
    <span class="field-label">{{ __('Categorii') }}</span>
    <div class="grid gap-1 rounded-xl border border-line p-2 sm:grid-cols-2">
        @foreach ($categories as $c)
            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-cream">
                <input type="checkbox" name="category_ids[]" value="{{ $c->id }}" class="rounded border-line text-forest" @checked(in_array($c->id, $chosen, true))>
                <span>{{ $c->icon }} {{ $c->t('name') }}</span>
                @if ($primary && $c->id === (int) $primary)<span class="ml-auto rounded-full bg-sand px-2 text-[10px] font-semibold">{{ __('principală') }}</span>@endif
            </label>
        @endforeach
    </div>
    <p class="field-help">{{ __('Bifează toate categoriile în care se încadrează produsele. Prima categorie bifată apare pe cardul de pe site.') }}</p>
    @error('category_ids')<p class="field-error">{{ $message }}</p>@enderror
</div>
