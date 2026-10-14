<x-layouts.crm :title="__('Rezervări').' — '.$event->title_ro">
    <x-slot:breadcrumb><a href="{{ route('crm.events.show', $event) }}" class="text-sm text-ink-soft hover:text-clay">← {{ $event->title_ro }}</a></x-slot:breadcrumb>
    <x-slot:actions><a href="{{ route('crm.events.map', $event) }}" class="btn-ghost">{{ __('Hartă') }}</a></x-slot:actions>
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('crm.reservations.index', $event) }}" @class(['chip', 'chip-active' => ! $filter])>{{ __('Active') }}</a>
        @foreach (\App\Enums\ReservationStatus::cases() as $st)
            <a href="{{ route('crm.reservations.index', [$event, 'status' => $st->value]) }}" @class(['chip', 'chip-active' => $filter === $st->value])>{{ $st->label() }}</a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Stand') }}</th><th>{{ __('Producător') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Necesități') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($reservations as $r)
                <tr>
                    <td class="font-semibold">{{ $r->slot->code }}<p class="text-xs font-normal text-ink-soft">{{ $r->slot->dimensions() }} · {{ money_ron($r->price) }}</p></td>
                    <td><a href="{{ route('crm.producers.show', $r->producer) }}" class="hover:text-clay">{{ $r->producer->brand_name }}</a><p class="text-xs text-ink-soft">{{ $r->producer->category?->name_ro }}</p></td>
                    <td><x-lang-badge :lang="$r->producer->preferred_language" /></td>
                    <td class="text-xs">
                        @if ($r->registration)
                            {{ $r->registration->needs_electricity ? '⚡ ' : '' }}{{ $r->registration->needs_table ? '🪑 ' : '' }}{{ $r->registration->needs_tent ? '⛺ ' : '' }}
                            <span class="block text-ink-soft">{{ Str::limit($r->registration->notes, 60) }}</span>
                        @endif
                    </td>
                    <td><x-status :status="$r->status" />@if ($r->hold_expires_at)<p class="text-xs text-ink-soft">⏱ {{ $r->hold_expires_at->format('H:i') }}</p>@endif</td>
                    <td class="whitespace-nowrap text-right">
                        @if (in_array($r->status->value, ['requested', 'hold']))
                            <form method="POST" action="{{ route('crm.reservations.approve', $r) }}" class="inline">@csrf<button class="btn-primary btn-sm">{{ __('Aprobă') }}</button></form>
                            <form method="POST" action="{{ route('crm.reservations.reject', $r) }}" class="inline" x-data @submit="const reason = prompt('{{ __('Motivul respingerii (opțional):') }}'); if (reason === null) { $event.preventDefault(); return; } $el.querySelector('[name=reason]').value = reason">@csrf<input type="hidden" name="reason"><button class="btn-ghost btn-sm">{{ __('Respinge') }}</button></form>
                        @elseif ($r->status->value === 'approved')
                            @can('contracts.manage')<form method="POST" action="{{ route('crm.reservations.annex', $r) }}" class="inline">@csrf<button class="btn-ghost btn-sm">{{ __('Generează anexă') }}</button></form>@endcan
                            <form method="POST" action="{{ route('crm.reservations.cancel', $r) }}" class="inline" onsubmit="return confirm('{{ __('Anulezi rezervarea aprobată?') }}')">@csrf<button class="btn-ghost btn-sm">{{ __('Anulează') }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-ink-soft">{{ __('Nicio rezervare.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $reservations->links() }}

    <h2 class="mt-10 text-2xl">{{ __('Prezență producători aprobați') }}</h2>
    <div class="card mt-4 overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Producător') }}</th><th>{{ __('Stand') }}</th><th>{{ __('Prezență') }}</th></tr></thead>
            <tbody>
            @foreach ($registrations as $reg)
                <tr>
                    <td>{{ $reg->producer->brand_name }}</td>
                    <td>{{ $reg->activeReservation?->slot?->code ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('crm.registrations.attendance', $reg) }}" class="flex gap-1">@csrf
                            <button name="attended" value="1" @class(['btn-sm btn', 'bg-forest text-cream' => $reg->attended === true, 'btn-ghost' => $reg->attended !== true])>{{ __('Prezent') }}</button>
                            <button name="attended" value="0" @class(['btn-sm btn', 'bg-red-700 text-white' => $reg->attended === false, 'btn-ghost' => $reg->attended !== false])>{{ __('Absent') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.crm>
