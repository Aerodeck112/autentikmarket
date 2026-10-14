@props(['dark' => false])
<form method="POST" action="{{ lroute('newsletter.store') }}" class="w-full">
    @csrf
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="flex flex-col gap-3 sm:flex-row">
        <label class="sr-only" for="nl-email">E-mail</label>
        <input id="nl-email" type="email" name="email" required placeholder="{{ __('Adresa ta de e-mail') }}" class="field-input !rounded-full !py-3 sm:flex-1">
        <button class="btn-accent btn-lg">{{ __('Abonează-te') }}</button>
    </div>
    <label class="mt-3 flex items-start gap-2 text-xs {{ ($dark ?? false) ? 'text-cream/70' : 'text-ink-soft' }}">
        <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-line text-clay focus:ring-clay/30">
        <span>{{ __('Sunt de acord să primesc newsletterul Autentik Market. Mă pot dezabona oricând.') }} <a href="{{ lroute('legal.privacy') }}" class="underline">{{ __('Confidențialitate') }}</a></span>
    </label>
</form>
