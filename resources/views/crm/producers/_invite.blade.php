{{-- Invitația pe platformă: pleacă doar dacă e-mailul din fișă este completat (e-mailul nu este obligatoriu). --}}
<div x-data="{ account: @js((bool) old('create_account', true)) }" class="space-y-3">
    <x-checkbox name="create_account" :label="__('Trimite invitație pe platformă')" :checked="old('create_account', true)" x-model="account"
                :help="__('Dacă e completat e-mailul, producătorul primește, în limba lui, un link să își aleagă parola (valabil 7 zile). Din cont își completează profilul, documentele și rezervă standul. Fără e-mail nu se trimite nimic; invitația pleacă automat când completezi adresa mai târziu.')" />
    <div x-show="account" x-cloak class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
        <x-input name="contact_first_name" :label="__('Prenume persoană de contact')" :value="old('contact_first_name')" />
        <x-input name="contact_last_name" :label="__('Nume persoană de contact')" :value="old('contact_last_name')" />
        <p class="text-xs text-ink-soft sm:col-span-2 lg:col-span-1 xl:col-span-2">{{ __('Opțional: dacă lipsesc, se folosește reprezentantul sau denumirea. E-mailul din fișă devine adresa de autentificare; dacă există deja un cont cu acest e-mail (de ex. un cumpărător), producătorul este legat de acel cont.') }}</p>
    </div>
</div>
