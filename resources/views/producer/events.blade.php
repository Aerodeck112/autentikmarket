<x-layouts.app :title="__('Târguri')" area="producer">
    <h1 class="text-3xl sm:text-4xl">{{ __('Următoarele târguri') }}</h1>
    @unless ($producer->isApproved())
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('Poți alege standuri după ce profilul tău este aprobat.') }}</div>
    @endunless
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse ($upcoming as $event)
            @php $reg = $registrations->get($event->id); @endphp
            <div class="card card-pad">
                <p class="eyebrow">{{ $event->localizedCity() }}</p>
                <h2 class="mt-1 text-2xl">{{ $event->t('title') }}</h2>
                <p class="mt-1 text-sm capitalize text-ink-soft">{{ format_date_local($event->event_date, true) }} · {{ $event->timeRange() }}</p>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm">
                        @if ($reg)<x-status :status="$reg->status" />@if ($reg->activeReservation?->slot)<span class="badge bg-cream">{{ $reg->activeReservation->slot->code }}</span>@endif
                        @else<span class="text-ink-soft">{{ $event->status->label() }}</span>@endif
                    </div>
                    <a href="{{ route('producer.events.show', $event) }}" class="{{ $producer->isApproved() && $event->registrationsOpen() && ! $reg ? 'btn-accent' : 'btn-ghost btn-sm' }}">
                        {{ $producer->isApproved() && $event->registrationsOpen() && ! $reg ? __('PARTICIPĂ') : __('Detalii') }}
                    </a>
                </div>
            </div>
        @empty
            <x-empty class="md:col-span-2" :title="__('Niciun târg programat')" :text="__('Te anunțăm când se deschid înscrierile pentru ediția următoare.')" />
        @endforelse
    </div>

    <h2 class="mt-12 text-2xl">{{ __('Istoric participări') }}</h2>
    <div class="card mt-4 overflow-hidden">
        <table class="table-crm">
            <thead><tr><th>{{ __('Eveniment') }}</th><th>{{ __('Data') }}</th><th>{{ __('Stand') }}</th><th>{{ __('Status') }}</th><th>{{ __('Prezență') }}</th></tr></thead>
            <tbody>
            @forelse ($history as $reg)
                <tr>
                    <td>{{ $reg->event->t('title') }}</td>
                    <td>{{ $reg->event->event_date->format('d.m.Y') }}</td>
                    <td>{{ $reg->reservations->first()?->slot?->code ?? '—' }}</td>
                    <td><x-status :status="$reg->status" /></td>
                    <td>{{ $reg->attended === null ? '—' : ($reg->attended ? __('Prezent') : __('Absent')) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-sm text-ink-soft">{{ __('Nicio participare încă.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
