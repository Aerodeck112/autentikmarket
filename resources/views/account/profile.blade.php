<x-layouts.app :title="__('Profil')">
    <h1 class="text-3xl sm:text-4xl">{{ __('Profilul meu') }}</h1>
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('account.profile.update') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <h2 class="text-xl">{{ __('Date personale') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="first_name" :label="__('Prenume')" :value="$user->first_name" required />
                <x-input name="last_name" :label="__('Nume')" :value="$user->last_name" required />
                <x-input name="email" type="email" :label="__('E-mail')" :value="$user->email" required />
                <x-input name="phone" :label="__('Telefon')" :value="$user->phone" />
                <x-input name="city" :label="__('Localitate')" :value="$user->city" class="sm:col-span-2" />
            </div>
            <x-select name="preferred_language" :label="__('Limba de comunicare')" :options="['ro' => 'Română', 'hu' => 'Magyar']" :value="$user->preferredLanguage()->value" :help="__('Toate mesajele automate îți vor fi trimise în această limbă.')" required />
            <p class="text-xs text-ink-soft">{{ __('Cont creat la') }}: {{ $user->created_at->format('d.m.Y') }} · {{ $user->member_code }}</p>
            <button class="btn-primary">{{ __('Salvează') }}</button>
        </form>

        <div class="space-y-6">
            <form method="POST" action="{{ route('account.consents') }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <h2 class="text-xl">{{ __('Comunicări (GDPR)') }}</h2>
                <x-checkbox name="newsletter" :label="__('Newsletter')" :checked="$user->newsletter" :help="__('Anunțuri despre târguri, în limba ta.')" />
                <x-checkbox name="marketing_consent" :label="__('Comunicări de marketing')" :checked="$user->marketing_consent" :help="__('Oferte și noutăți de la partenerii comunității.')" />
                <button class="btn-ghost btn-sm">{{ __('Salvează preferințele') }}</button>
            </form>

            <form method="POST" action="{{ route('account.password') }}" class="card card-pad space-y-4">
                @csrf @method('PUT')
                <h2 class="text-xl">{{ __('Schimbă parola') }}</h2>
                <x-input name="current_password" type="password" :label="__('Parola actuală')" autocomplete="current-password" required />
                <x-input name="password" type="password" :label="__('Parolă nouă')" autocomplete="new-password" required />
                <x-input name="password_confirmation" type="password" :label="__('Confirmă parola')" autocomplete="new-password" required />
                <button class="btn-ghost btn-sm">{{ __('Schimbă parola') }}</button>
            </form>

            <form method="POST" action="{{ route('account.destroy') }}" class="card card-pad space-y-3 border-red-200" onsubmit="return confirm('{{ __('Ești sigur? Datele personale vor fi șterse definitiv.') }}')">
                @csrf @method('DELETE')
                <h2 class="text-xl text-red-800">{{ __('Șterge contul') }}</h2>
                <p class="text-sm text-ink-soft">{{ __('Datele personale sunt anonimizate definitiv. Statisticile agregate ale târgurilor se păstrează fără a te identifica.') }}</p>
                <x-input name="password" type="password" :label="__('Confirmă cu parola')" required />
                <button class="btn-danger btn-sm">{{ __('Șterge contul') }}</button>
            </form>
        </div>
    </div>
</x-layouts.app>
