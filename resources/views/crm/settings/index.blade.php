<x-layouts.crm :title="__('Setări')">
    <x-slot:actions>
        @can('users.manage')<a href="{{ route('crm.users.index') }}" class="btn-ghost">{{ __('Utilizatori & roluri') }}</a>@endcan
        <a href="{{ route('crm.messages.index') }}" class="btn-ghost">{{ __('Mesaje contact') }}</a>
    </x-slot:actions>
    <form method="POST" action="{{ route('crm.settings.update') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-2">@csrf @method('PUT')
        <div class="card card-pad space-y-4">
            <h2 class="text-xl">{{ __('Integrări') }}</h2>
            <x-input name="integrations_shop_url" type="url" :label="__('Autentik Shop URL')" :value="setting('integrations.shop_url')" placeholder="https://" :help="__('Magazinul online extern. Gol = linkurile către shop sunt ascunse.')" />
        </div>
        <div class="card card-pad space-y-4">
            <h2 class="text-xl">{{ __('Rezervări & documente') }}</h2>
            <x-input name="reservations_hold_minutes" type="number" :label="__('Durata hold-ului temporar pe stand (minute)')" :value="setting('reservations.hold_minutes')" required />
            <x-input name="contracts_number_prefix" :label="__('Prefix numerotare contracte/anexe')" :value="setting('contracts.number_prefix')" required />
        </div>
        <div class="card card-pad space-y-4">
            <h2 class="text-xl">{{ __('Organizator (date pentru contracte)') }}</h2>
            <p class="text-xs text-amber-700">{{ __('Completați cu datele reale ale organizatorului — nu sunt inventate de sistem.') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="org_name" :label="__('Nume afișat')" :value="setting('org.name')" />
                <x-input name="org_legal_name" :label="__('Denumire juridică')" :value="setting('org.legal_name')" />
                <x-input name="org_cui" label="CUI" :value="setting('org.cui')" />
                <x-input name="org_trade_register" :label="__('Nr. Reg. Com.')" :value="setting('org.trade_register')" />
                <x-input name="org_address" :label="__('Adresă sediu')" :value="setting('org.address')" class="sm:col-span-2" />
                <x-input name="org_representative" :label="__('Reprezentant legal')" :value="setting('org.representative')" />
                <x-input name="org_iban" label="IBAN" :value="setting('org.iban')" />
                <x-input name="org_email" type="email" :label="__('E-mail public')" :value="setting('org.email')" />
                <x-input name="org_phone" :label="__('Telefon public')" :value="setting('org.phone')" />
            </div>
        </div>
        <div class="card card-pad space-y-4">
            <h2 class="text-xl">{{ __('Identitate & social media') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><x-input name="logo" type="file" :label="__('Logo oficial (PNG/SVG→PNG)')" accept="image/*" />@if (setting('brand.logo_path'))<label class="mt-1 flex items-center gap-1 text-xs"><input type="checkbox" name="remove_logo" value="1"> {{ __('elimină') }}</label>@endif</div>
                <div><x-input name="hero" type="file" :label="__('Fotografie hero homepage')" accept="image/*" />@if (setting('brand.hero_path'))<label class="mt-1 flex items-center gap-1 text-xs"><input type="checkbox" name="remove_hero" value="1"> {{ __('elimină') }}</label>@endif</div>
                <x-input name="social_facebook" type="url" label="Facebook" :value="setting('social.facebook')" />
                <x-input name="social_instagram" type="url" label="Instagram" :value="setting('social.instagram')" />
                <x-input name="social_youtube" type="url" label="YouTube" :value="setting('social.youtube')" />
                <x-input name="mail_reply_to" type="email" :label="__('Reply-to pentru e-mailuri')" :value="setting('mail.reply_to')" />
            </div>
            <x-input name="seo_default_description_ro" :label="__('Descriere SEO implicită RO')" :value="setting('seo.default_description_ro')" />
            <x-input name="seo_default_description_hu" :label="__('Descriere SEO implicită HU')" :value="setting('seo.default_description_hu')" />
        </div>
        <div class="xl:col-span-2"><button class="btn-primary btn-lg">{{ __('Salvează setările') }}</button></div>
    </form>

    <div class="card card-pad mt-6 max-w-2xl">
        <h2 class="text-xl">{{ __('Setări e-mail (SMTP)') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Configurate în fișierul .env (MAIL_*), compatibile cu e-mail cPanel sau SMTP extern.') }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
            @foreach ($mail as $k => $v)<div><dt class="text-xs text-ink-soft">{{ $k }}</dt><dd class="font-mono">{{ $v ?: '—' }}</dd></div>@endforeach
        </dl>
        <form method="POST" action="{{ route('crm.settings.test-email') }}" class="mt-4">@csrf<button class="btn-accent">✉️ {{ __('Trimite email test') }}</button></form>
    </div>
</x-layouts.crm>
