@php
$faq = app()->getLocale() === 'hu' ? [
    ['Mikor van a vásár?', 'Az Autentik Market havonta egyszer, általában szombaton van. A pontos dátumokat és a programot a vásárnaptárban találod.'],
    ['Mi az Autentik kártya?', 'Ingyenes digitális kártya QR-kóddal. A bejáratnál felmutatod, és minden látogatásod rögzítésre kerül. Bizonyos számú látogatás után ajándék jár.'],
    ['Hogyan vehetek részt a tombolán?', 'Ha egy vásárhoz tombola tartozik, a kártyás beléptetéskor automatikusan bekerülsz, vagy fiókodból jelentkezhetsz. Az eredményt a fiókodban látod.'],
    ['Hogyan írhatok értékelést?', 'Ha egy termelőnél vásároltál, olvasd be a standján lévő QR-kódot („Itt vásároltam”). A vásár után e-mailben meghívunk egy rövid értékelésre.'],
    ['Termelő vagyok. Hogyan jelentkezhetek?', 'Hozz létre termelői fiókot, töltsd ki a profilt, töltsd fel a dokumentumokat és küldd el ellenőrzésre. Jóváhagyás után a térképen választhatsz standot.'],
    ['Mennyibe kerül egy stand?', 'A díjak vásáronként és standonként eltérhetnek; a térképen, a stand kiválasztásakor jelennek meg, vagy a szervezők közlik.'],
    ['Milyen nyelven kommunikáltok?', 'Románul és magyarul. Regisztrációkor kiválasztod, melyik nyelven szeretnél üzeneteket kapni.'],
] : [
    ['Când are loc târgul?', 'Autentik Market are loc lunar, de obicei sâmbăta. Datele exacte și programul le găsești în calendarul târgurilor.'],
    ['Ce este cardul Autentik?', 'Un card digital gratuit, cu cod QR. Îl arăți la intrare și fiecare vizită se înregistrează. După un anumit număr de vizite primești cadouri.'],
    ['Cum particip la tombolă?', 'Dacă un târg are tombolă, ești înscris automat la check-in cu cardul sau te poți înscrie din cont. Rezultatul îl vezi în contul tău.'],
    ['Cum las o recenzie?', 'Dacă ai cumpărat de la un producător, scanează codul QR de la standul lui („Am cumpărat de aici”). După târg primești pe e-mail invitația la o recenzie scurtă.'],
    ['Sunt producător. Cum mă înscriu?', 'Creează un cont de producător, completează profilul, încarcă documentele și trimite-le spre verificare. După aprobare îți alegi standul pe hartă.'],
    ['Cât costă un stand?', 'Tarifele pot diferi de la un târg la altul și de la un stand la altul; sunt afișate pe hartă la alegerea standului sau comunicate de organizatori.'],
    ['În ce limbă comunicați?', 'În română și în maghiară. La înregistrare alegi limba în care vrei să primești mesajele.'],
];
@endphp
<x-layouts.site :title="__('Întrebări frecvente')">
    <section class="container-site max-w-3xl pt-10">
        <p class="eyebrow">FAQ</p>
        <h1 class="mt-3 text-5xl">{{ __('Întrebări frecvente') }}</h1>
        <div class="mt-10 divide-y divide-line border-y border-line">
            @foreach ($faq as $i => [$q, $a])
                <details class="group py-5" @if ($i === 0) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-display text-xl">
                        {{ $q }} <span class="text-clay transition group-open:rotate-45">＋</span>
                    </summary>
                    <p class="mt-3 text-ink-soft">{{ $a }}</p>
                </details>
            @endforeach
        </div>
        <p class="mt-10 text-ink-soft">{{ __('Nu ai găsit răspunsul?') }} <a href="{{ lroute('contact') }}" class="text-clay underline">{{ __('Scrie-ne') }}</a></p>
    </section>
</x-layouts.site>
