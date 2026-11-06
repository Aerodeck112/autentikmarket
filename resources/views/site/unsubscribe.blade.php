<x-layouts.site :title="__('Dezabonare')" noindex>
    <section class="container-site flex justify-center py-16">
        <div class="card card-pad w-full max-w-lg text-center sm:p-10">
            @if ($done)
                <p class="text-4xl">✓</p>
                <h1 class="mt-3 text-3xl">{{ __('Te-ai dezabonat') }}</h1>
                <p class="mt-3 text-ink-soft">{{ __('Nu vei mai primi campaniile noastre pe adresa :e. Mesajele legate de contul tău (de exemplu confirmări) pot sosi în continuare.', ['e' => $recipient->email]) }}</p>
            @else
                <h1 class="text-3xl">{{ __('Dezabonare de la e-mailurile Autentik Market') }}</h1>
                <p class="mt-3 text-ink-soft">{{ __('Confirmă că nu mai vrei să primești campaniile noastre pe adresa :e.', ['e' => $recipient->email]) }}</p>
                <form method="POST" action="{{ URL::signedRoute('campaigns.unsubscribe.store', ['recipient' => $recipient->id]) }}" class="mt-6">@csrf
                    <button class="btn-primary btn-lg">{{ __('Mă dezabonez') }}</button>
                </form>
            @endif
            <a href="{{ lroute('home', [], $recipient->language) }}" class="mt-6 inline-block text-sm text-clay hover:underline">{{ __('Înapoi la site') }}</a>
        </div>
    </section>
</x-layouts.site>
