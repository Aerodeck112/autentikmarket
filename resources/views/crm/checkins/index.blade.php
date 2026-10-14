<x-layouts.crm :title="__('Check-in')">
    <x-slot:actions><a href="{{ route('staff.index') }}" class="btn-accent">📱 {{ __('Deschide scannerul (Staff PWA)') }}</a></x-slot:actions>
    <div class="mb-6 flex flex-wrap items-end gap-4">
        <form><select name="event" onchange="this.form.submit()" class="field-input">@foreach ($events as $e)<option value="{{ $e->id }}" @selected($event && $e->id === $event->id)>{{ $e->title_ro }} · {{ $e->event_date->format('d.m.Y') }}</option>@endforeach</select></form>
        @if ($event)
            <form method="POST" action="{{ route('crm.checkins.store') }}" class="flex gap-2">@csrf
                <input type="hidden" name="event_id" value="{{ $event->id }}">
                <input name="member_code" placeholder="AM-XXXXXX" class="field-input !w-40 font-mono uppercase" required>
                <button class="btn-primary">{{ __('Check-in manual') }}</button>
            </form>
            @can('exports.run')<a href="{{ route('crm.events.export', [$event, 'visitors', 'format' => 'xlsx']) }}" class="btn-ghost">XLSX</a>@endcan
        @endif
    </div>
    @if ($checkins)
        <div class="card overflow-x-auto">
            <table class="table-crm">
                <thead><tr><th>{{ __('Vizitator') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Ora') }}</th><th>{{ __('Metodă') }}</th><th>{{ __('Operator') }}</th></tr></thead>
                <tbody>
                @forelse ($checkins as $c)
                    <tr><td><a href="{{ route('crm.visitors.show', $c->user) }}" class="hover:text-clay">{{ $c->user->name }}</a> <span class="font-mono text-xs text-ink-soft">{{ $c->user->member_code }}</span></td><td><x-lang-badge :lang="$c->user->preferred_language" /></td><td>{{ $c->checked_in_at->format('H:i') }}</td><td>{{ $c->method === 'qr' ? 'QR' : __('manual') }}</td><td class="text-sm">{{ $c->operator?->name }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-ink-soft">{{ __('Niciun check-in.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $checkins->links() }}
    @endif
</x-layouts.crm>
