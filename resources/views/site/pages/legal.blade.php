@php
$titles = ['terms' => __('Termeni și condiții'), 'privacy' => __('Politica de confidențialitate'), 'cookies' => __('Politica de cookies')];
@endphp
<x-layouts.site :title="$titles[$page]">
    <section class="container-site max-w-3xl pt-10">
        <p class="eyebrow">{{ __('Informații legale') }}</p>
        <h1 class="mt-3 text-5xl">{{ $titles[$page] }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ __('Versiunea') }} {{ config('autentik.legal_versions.'.($page === 'cookies' ? 'privacy' : $page)) }}</p>
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ __('Document schelet: textul final trebuie redactat și validat juridic înainte de lansare.') }}
        </div>
        <div class="prose-am mt-8">
            @if ($page === 'cookies')
                <p>{{ __('Site-ul folosește exclusiv cookie-uri strict necesare:') }}</p>
                <ul>
                    <li><strong>autentik-market-session</strong> — {{ __('sesiunea de autentificare (expiră la închiderea sesiunii / după inactivitate).') }}</li>
                    <li><strong>XSRF-TOKEN</strong> — {{ __('protecție împotriva atacurilor CSRF.') }}</li>
                    <li><strong>locale</strong> — {{ __('limba aleasă (RO/HU), 12 luni.') }}</li>
                </ul>
                <p>{{ __('Nu folosim cookie-uri de analiză sau publicitate. Fonturile sunt găzduite local, fără servicii terțe.') }}</p>
            @elseif ($page === 'privacy')
                <h2>{{ __('Operator') }}</h2>
                <p>{{ setting('org.legal_name') }}, CUI {{ setting('org.cui') }}, {{ setting('org.address') }}.</p>
                <h2>{{ __('Ce date prelucrăm') }}</h2>
                <p>{{ __('Date de cont (nume, e-mail, telefon, localitate, limba preferată), vizitele la târg (check-in), recompensele, participarea la tombole, recenziile și, pentru producători, datele de firmă și documentele încărcate.') }}</p>
                <h2>{{ __('Consimțăminte separate') }}</h2>
                <p>{{ __('Acceptarea termenilor nu înseamnă acord pentru marketing. Acordul pentru newsletter și pentru comunicări de marketing se dau separat și pot fi retrase oricând din cont.') }}</p>
                <h2>{{ __('Drepturile tale') }}</h2>
                <p>{{ __('Acces, rectificare, ștergere (anonimizare), restricționare, portabilitate, opoziție. Contul poate fi șters direct din pagina de profil.') }}</p>
                <p class="placeholder">[{{ __('DE COMPLETAT DE JURIST: temeiuri legale, durate de stocare, împuterniciți, transferuri, DPO, ANSPDCP.') }}]</p>
            @else
                <p class="placeholder">[{{ __('DE COMPLETAT DE JURIST: condițiile de utilizare a platformei, a cardului Autentik, a programului de fidelitate, a tombolelor și a recenziilor.') }}]</p>
            @endif
        </div>
    </section>
</x-layouts.site>
