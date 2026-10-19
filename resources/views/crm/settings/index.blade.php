<x-layouts.crm :title="__('Setări')">
    <x-slot:actions>
        @can('users.manage')<a href="{{ route('crm.users.index') }}" class="btn-ghost">{{ __('Utilizatori & roluri') }}</a>@endcan
        <a href="{{ route('crm.messages.index') }}" class="btn-ghost">{{ __('Mesaje contact') }}</a>
    </x-slot:actions>
    {{-- E-MAIL (SMTP): configurat din CRM, fără .env --}}
    <div id="email" class="card card-pad mb-6" x-data="{ enc: @js(old('smtp_encryption', $mail['encryption'])) }">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl">{{ __('E-mail (SMTP)') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Contul de e-mail din care pleacă toate mesajele automate. Datele le găsești în cPanel → Email Accounts → Connect Devices.') }}</p>
            </div>
            @if (! $mail['delivers'])
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">{{ __('E-mailurile NU se trimit') }}</span>
            @elseif ($mail['from_crm'])
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ __('Configurat din CRM') }}</span>
            @else
                <span class="rounded-full bg-sand px-3 py-1 text-xs font-bold text-ink-soft">{{ __('Configurat din .env') }}</span>
            @endif
        </div>

        @if (! $mail['delivers'])
            <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ __('Aplicația este în modul „:m”: mesajele apar ca trimise în jurnal, dar sunt doar scrise în log și nu ajung la nimeni. Completează datele de mai jos și apasă „Salvează și trimite test”.', ['m' => $mail['mailer']]) }}</p>
        @endif
        @if (! $mail['heartbeat'])
            <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Cron-ul nu a rulat încă: mesajele automate rămân în coadă (acum: :n) până când adaugi cron-ul în cPanel → Cron Jobs (vezi CRM → Sistem). Testul de mai jos se trimite imediat, fără cron.', ['n' => $mail['queued']]) }}</p>
        @endif

        <form method="POST" action="{{ route('crm.settings.mail') }}" class="mt-5 grid gap-4 md:grid-cols-2" autocomplete="off">@csrf @method('PUT')
            <x-input name="smtp_host" :label="__('Server SMTP')" :value="$mail['host']" placeholder="mail.autentikmarket.ro" required />
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label" for="smtp_encryption">{{ __('Criptare') }}</label>
                    <select id="smtp_encryption" name="smtp_encryption" class="field-input" x-model="enc" @change="$refs.port.value = enc === 'tls' ? 587 : 465">
                        <option value="ssl">SSL (465)</option>
                        <option value="tls">TLS / STARTTLS (587)</option>
                    </select>
                </div>
                <x-input name="smtp_port" type="number" :label="__('Port')" :value="$mail['port'] ?: 465" x-ref="port" required />
            </div>
            <x-input name="smtp_username" :label="__('Utilizator (adresa de e-mail completă)')" :value="$mail['username']" placeholder="noreply@autentikmarket.ro" />
            <x-input name="smtp_password" type="password" :label="__('Parola contului de e-mail')" autocomplete="new-password" :placeholder="$mail['has_password'] ? '•••••••• '.__('(salvată — lasă gol ca să o păstrezi)') : ''" />
            <x-input name="from_address" type="email" :label="__('Adresa „De la”')" :value="$mail['from_address']" :help="__('De regulă aceeași cu utilizatorul; altfel serverul poate refuza mesajele.')" />
            <x-input name="from_name" :label="__('Numele expeditorului')" :value="$mail['from_name']" placeholder="Autentik Market" />
            <x-checkbox name="smtp_verify_peer" class="md:col-span-2" :label="__('Verifică certificatul SSL al serverului (recomandat)')" :checked="$mail['verify_peer']" :help="__('Debifează doar dacă testul spune că certificatul nu corespunde numelui serverului.')" />
            <div class="flex flex-wrap gap-2 md:col-span-2">
                <button name="action" value="test" class="btn-accent">✉️ {{ __('Salvează și trimite test') }}</button>
                <button name="action" value="save" class="btn-ghost">{{ __('Doar salvează') }}</button>
                @if ($mail['from_crm'])
                    <button name="action" value="clear" formnovalidate class="btn-ghost" onclick="return confirm(@js(__('Ștergi setările SMTP din CRM? Se vor folosi valorile din .env.')))">{{ __('Folosește .env') }}</button>
                @endif
            </div>
            <p class="text-xs text-ink-soft md:col-span-2">{{ __('Testul se trimite imediat la adresa ta (:e) și îți arată exact ce eroare dă serverul, dacă nu merge.', ['e' => auth()->user()->email]) }}</p>
        </form>
    </div>

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

</x-layouts.crm>
