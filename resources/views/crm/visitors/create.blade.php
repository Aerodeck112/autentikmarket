<x-layouts.crm :title="__('Cumpărător nou')">
    <x-slot:breadcrumb><a href="{{ route('crm.visitors.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Cumpărători') }}</a></x-slot:breadcrumb>
    <form method="POST" action="{{ route('crm.visitors.store') }}" class="grid max-w-4xl gap-6 lg:grid-cols-2" x-data="{ email: @js(old('email', '')) }">
        @csrf
        <div class="card card-pad space-y-4">
            <h2 class="text-lg">{{ __('Date de contact') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="first_name" :label="__('Prenume')" required />
                <x-input name="last_name" :label="__('Nume')" required />
            </div>
            <x-input name="email" type="email" label="E-mail" x-model="email" :help="__('Opțional. Cu e-mail, cumpărătorul își poate deschide contul și vedea cardul în telefon.')" />
            <x-input name="phone" :label="__('Telefon')" :help="__('Obligatoriu dacă nu are e-mail: după el îl găsiți la check-in.')" />
            <x-input name="city" :label="__('Localitate')" />
            <x-select name="preferred_language" :label="__('Limba de comunicare')" :options="['ro' => 'Română (RO)', 'hu' => 'Magyar (HU)']" :value="old('preferred_language', 'ro')" required />
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-4">
                <h2 class="text-lg">{{ __('Acorduri (GDPR)') }}</h2>
                <x-checkbox name="consent_terms" :label="__('Persoana a acceptat termenii și politica de confidențialitate')" :help="__('De exemplu pe formularul tipărit de la intrare. Acordul se înregistrează cu numele tău și data de azi.')" />
                <x-checkbox name="newsletter" :label="__('Dorește newsletter')" :help="__('Bifează doar dacă persoana a cerut explicit.')" />
                <x-checkbox name="marketing" :label="__('Acceptă comunicări despre târguri (marketing)')" :help="__('Bifează doar dacă persoana a cerut explicit.')" />
            </div>
            <div class="card card-pad space-y-3">
                <h2 class="text-lg">{{ __('Vizită la târg (opțional)') }}</h2>
                <x-crm.visit-fields :events="$events" :producers="$producers" :participants="$participants" />
            </div>
            <div class="card card-pad space-y-3" x-show="email" x-cloak>
                <x-checkbox name="send_invite" :label="__('Trimite invitație pe e-mail')" :checked="old('send_invite', true)" :help="__('Primește, în limba lui, un link să își aleagă parola și să vadă cardul Autentik (valabil 7 zile).')" />
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Adaugă cumpărătorul') }}</button>
            <p class="text-xs text-ink-soft">{{ __('Cumpărătorii se pot înscrie și singuri, de pe site, din „Creează cardul Autentik”.') }}</p>
        </div>
    </form>
</x-layouts.crm>
