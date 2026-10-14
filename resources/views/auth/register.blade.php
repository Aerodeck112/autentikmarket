<x-layouts.site :title="$producer ? __('Înregistrare producător') : __('Creează cardul Autentik')" noindex>
    <section class="container-site flex justify-center py-12 sm:py-16">
        <div class="w-full max-w-2xl">
            <div class="mb-8 text-center">
                <p class="eyebrow">{{ $producer ? __('Pentru producători și artizani') : __('Card Autentik — gratuit') }}</p>
                <h1 class="mt-3 text-4xl sm:text-5xl">{{ $producer ? __('Înregistrare producător') : __('Creează cardul Autentik') }}</h1>
                <p class="mx-auto mt-3 max-w-lg text-ink-soft">
                    {{ $producer ? __('Creează contul, apoi completează profilul și documentele pentru verificare.') : __('Fiecare vizită la târg se adaugă pe card. La 5 și 10 vizite te așteaptă cadouri.') }}
                </p>
            </div>
            <form method="POST" action="{{ $producer ? lroute('register.producer.store') : lroute('register.store') }}" class="card card-pad space-y-5 sm:p-8">
                @csrf
                <x-flash />

                {{-- LIMBA DE COMUNICARE — obligatorie, preselectată după versiunea site-ului --}}
                <fieldset class="rounded-2xl bg-cream p-4">
                    <legend class="field-label !mb-3 px-1 text-base">{{ __('În ce limbă dorești să comunicăm?') }} <span class="text-clay">*</span></legend>
                    @php $currentLang = old('preferred_language', app()->getLocale()); @endphp
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (['ro' => 'Română', 'hu' => 'Magyar'] as $code => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 has-[:checked]:border-forest has-[:checked]:ring-2 has-[:checked]:ring-forest/20">
                                <input type="radio" name="preferred_language" value="{{ $code }}" @checked($currentLang === $code) required class="text-forest focus:ring-forest/30">
                                <span class="font-medium">{{ $label }}</span>
                                <x-lang-badge :lang="$code" class="ml-auto" />
                            </label>
                        @endforeach
                    </div>
                    <p class="field-help">{{ __('Toate e-mailurile, notificările și reminderele vor fi trimise în această limbă.') }}</p>
                    @error('preferred_language')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>

                @if ($producer)
                    <x-input name="brand_name" :label="__('Denumirea brandului / fermei / atelierului')" required />
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="first_name" :label="__('Prenume')" autocomplete="given-name" required />
                    <x-input name="last_name" :label="__('Nume')" autocomplete="family-name" required />
                    <x-input name="email" type="email" :label="__('E-mail')" autocomplete="email" required />
                    <x-input name="phone" type="tel" :label="__('Telefon')" autocomplete="tel" />
                    <x-input name="city" :label="__('Localitate')" autocomplete="address-level2" class="sm:col-span-2" />
                    <x-input name="password" type="password" :label="__('Parolă')" autocomplete="new-password" :help="__('Minim 8 caractere, litere și cifre.')" required />
                    <x-input name="password_confirmation" type="password" :label="__('Confirmă parola')" autocomplete="new-password" required />
                </div>

                <div class="space-y-3 border-t border-line pt-5">
                    @php
                        $termsLabel = e(__('Accept')).' <a class="text-clay underline" target="_blank" href="'.e(lroute('legal.terms')).'">'.e(__('termenii și condițiile')).'</a> *';
                        $privacyLabel = e(__('Am citit')).' <a class="text-clay underline" target="_blank" href="'.e(lroute('legal.privacy')).'">'.e(__('politica de confidențialitate')).'</a> *';
                    @endphp
                    <x-checkbox name="terms" :label="$termsLabel" />
                    <x-checkbox name="privacy" :label="$privacyLabel" />
                    <x-checkbox name="newsletter" :label="__('Vreau să primesc newsletterul (opțional)')" />
                    <x-checkbox name="marketing" :label="__('Sunt de acord să primesc comunicări de marketing (opțional)')" />
                </div>

                <button class="btn-primary btn-lg w-full">{{ $producer ? __('Creează contul de producător') : __('Creează cardul Autentik') }}</button>
                <p class="text-center text-sm text-ink-soft">{{ __('Ai deja cont?') }} <a href="{{ lroute('login') }}" class="text-clay underline">{{ __('Intră în cont') }}</a></p>
            </form>
        </div>
    </section>
</x-layouts.site>
