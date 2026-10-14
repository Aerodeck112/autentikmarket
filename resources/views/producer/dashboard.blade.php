<x-layouts.app :title="__('Zona producător')" area="producer">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">{{ __('Zona producător') }}</p>
            <h1 class="mt-2 text-3xl sm:text-4xl">{{ $producer->brand_name }}</h1>
            <div class="mt-2 flex items-center gap-2 text-sm text-ink-soft">
                <x-status :status="$producer->status" /> <x-lang-badge :lang="$producer->preferred_language" />
                @if ($producer->isApproved())<a href="{{ lroute('producers.show', $producer->slug) }}" target="_blank" class="text-clay underline">{{ __('Profil public') }} ↗</a>@endif
            </div>
        </div>
    </div>

    <div class="mt-6">@include('producer._status-steps')</div>

    @if ($producer->status->value === 'rejected' && $producer->rejection_reason)
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">{{ __('Motiv') }}: {{ $producer->rejection_reason }}</div>
    @endif

    @if (! $producer->isApproved())
        <div class="card card-pad mt-6">
            <h2 class="text-xl">{{ __('Pașii pentru aprobare') }}</h2>
            @if ($openRequests->isNotEmpty())
                <div class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">{{ __('Documente solicitate de echipă:') }}</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($openRequests as $r)<li>{{ $r->document_type->label() }}@if ($r->note) — {{ $r->note }}@endif</li>@endforeach</ul>
                </div>
            @endif
            @if ($missing)
                <p class="mt-3 text-sm text-ink-soft">{{ __('Mai lipsesc:') }}</p>
                <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                    @php $fieldLabels = ['brand_name' => __('Denumire brand'), 'legal_name' => __('Denumire juridică'), 'cui' => __('CUI'), 'representative_name' => __('Reprezentant'), 'phone' => __('Telefon'), 'email' => __('E-mail'), 'city' => __('Localitate'), 'producer_category_id' => __('Categorie'), 'description_ro' => __('Descriere scurtă (RO)'), 'documents' => __('Cel puțin un document')]; @endphp
                    @foreach ($missing as $m)<li>○ {{ $fieldLabels[$m] ?? $m }}</li>@endforeach
                </ul>
                <div class="mt-4 flex gap-2"><a href="{{ route('producer.profile') }}" class="btn-primary btn-sm">{{ __('Completează profilul') }}</a><a href="{{ route('producer.documents') }}" class="btn-ghost btn-sm">{{ __('Documente') }}</a></div>
            @elseif (in_array($producer->status->value, ['new', 'incomplete', 'rejected']))
                <form method="POST" action="{{ route('producer.submit') }}" class="mt-4">@csrf<button class="btn-accent">{{ __('Trimite spre verificare') }}</button></form>
            @else
                <p class="mt-3 text-sm text-ink-soft">{{ __('Profilul tău este în verificare. Te anunțăm pe e-mail, în limba aleasă.') }}</p>
            @endif
        </div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Participări')" :value="$producer->registrations_count" :href="route('producer.events')" />
        <x-stat :label="__('Rating')" :value="$rating ? '★ '.$rating : '—'" :hint="$producer->approved_reviews_count.' '.__('recenzii')" :href="route('producer.reviews')" />
        <x-stat :label="__('Limba de comunicare')" :value="$producer->preferredLanguage()->label()" />
    </div>

    @if ($registrations->isNotEmpty())
        <div class="card card-pad mt-6">
            <h2 class="text-xl">{{ __('Participările mele') }}</h2>
            @foreach ($registrations as $reg)
                <a href="{{ route('producer.events.show', $reg->event) }}" class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3 hover:text-clay">
                    <span>{{ $reg->event->t('title') }} · {{ format_date_local($reg->event->event_date) }}</span>
                    <span class="flex items-center gap-2">@if ($reg->activeReservation?->slot)<span class="badge bg-cream">{{ $reg->activeReservation->slot->code }}</span>@endif<x-status :status="$reg->status" /></span>
                </a>
            @endforeach
        </div>
    @endif

    <h2 class="mt-10 text-2xl">{{ __('Următoarele târguri') }}</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @forelse ($upcoming as $event)
            <div class="card card-pad flex items-center justify-between gap-4">
                <div>
                    <p class="font-display text-xl">{{ $event->t('title') }}</p>
                    <p class="text-sm text-ink-soft">{{ format_date_local($event->event_date, true) }} · {{ $event->timeRange() }}</p>
                    @if (! $event->registrationsOpen())<p class="mt-1 text-xs text-ink-soft">{{ $event->status->label() }}</p>@endif
                </div>
                @if ($producer->isApproved() && $event->registrationsOpen())
                    <a href="{{ route('producer.events.show', $event) }}" class="btn-accent">{{ __('PARTICIPĂ') }}</a>
                @else
                    <a href="{{ route('producer.events.show', $event) }}" class="btn-ghost btn-sm">{{ __('Detalii') }}</a>
                @endif
            </div>
        @empty
            <x-empty class="md:col-span-2" :title="__('Niciun târg programat')" :text="__('Te anunțăm când se deschid înscrierile pentru ediția următoare.')" />
        @endforelse
    </div>
</x-layouts.app>
