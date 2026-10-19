<x-layouts.crm :title="__('Producător nou')">
    <x-slot:breadcrumb><a href="{{ route('crm.producers.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Producători') }}</a></x-slot:breadcrumb>
    <form method="POST" action="{{ route('crm.producers.store') }}" class="grid gap-6 lg:grid-cols-3" x-data="{ account: @js((bool) old('create_account', true)) }">
        @csrf
        @include('crm.producers._fields')
            <div class="card card-pad space-y-4">
                <h2 class="text-lg">{{ __('Cont și statut') }}</h2>
                <x-checkbox name="create_account" :label="__('Creează cont pentru producător')" :checked="old('create_account', true)" x-model="account"
                            :help="__('Primește pe e-mail, în limba lui, un link să își aleagă parola (valabil 7 zile). Din cont își completează profilul, documentele și rezervă standul.')" />
                <div x-show="account" x-cloak class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <x-input name="contact_first_name" :label="__('Prenume persoană de contact')" :value="old('contact_first_name')" />
                    <x-input name="contact_last_name" :label="__('Nume persoană de contact')" :value="old('contact_last_name')" />
                    <p class="text-xs text-ink-soft sm:col-span-2 lg:col-span-1 xl:col-span-2">{{ __('E-mailul din fișă devine adresa de autentificare. Dacă există deja un cont cu acest e-mail (de ex. un cumpărător), producătorul este legat de acel cont.') }}</p>
                </div>
                <p x-show="!account" x-cloak class="text-xs text-ink-soft">{{ __('Fără cont: producătorul apare în CRM, poate primi stand alocat de voi și primește e-mailurile automate la adresa din fișă. Contul îl poți crea oricând mai târziu.') }}</p>
                @can('producers.approve')
                    <x-checkbox name="approve" :label="__('Aprobă direct (fără verificarea documentelor)')" :checked="old('approve', false)"
                                :help="__('Producătorul poate primi imediat stand; documentele le puteți completa ulterior.')" />
                @endcan
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Adaugă producătorul') }}</button>
        </div>
    </form>
</x-layouts.crm>
