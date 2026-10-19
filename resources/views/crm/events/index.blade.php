<x-layouts.crm :title="__('Evenimente')">
    <x-slot:actions>
        @can('events.manage')<a href="{{ route('crm.events.create') }}" class="btn-primary">+ {{ __('Eveniment nou') }}</a>@endcan
    </x-slot:actions>
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('crm.events.index') }}" @class(['chip', 'chip-active' => ! $status])>{{ __('Toate') }}</a>
        @foreach (\App\Enums\EventStatus::cases() as $st)
            <a href="{{ route('crm.events.index', ['status' => $st->value]) }}" @class(['chip', 'chip-active' => $status === $st->value])>{{ $st->label() }}</a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Eveniment') }}</th><th>{{ __('Data') }}</th><th>{{ __('Oraș') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Producători') }}</th><th class="text-right">{{ __('Vizitatori') }}</th></tr></thead>
            <tbody>
            @forelse ($events as $event)
                <tr>
                    <td><a href="{{ route('crm.events.show', $event) }}" class="font-medium hover:text-clay">{{ $event->title_ro }}</a><p class="text-xs text-ink-soft">@if ($event->edition)<span class="font-semibold text-clay">{{ $event->editionLabel('ro') }}</span> · @endif{{ $event->title_hu }}</p></td>
                    <td class="whitespace-nowrap">{{ $event->event_date->format('d.m.Y') }}</td>
                    <td>{{ $event->city }}</td>
                    <td><x-status :status="$event->status" /></td>
                    <td class="text-right">{{ $event->approved_registrations_count }}</td>
                    <td class="text-right">{{ $event->checkins_count }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-ink-soft">{{ __('Niciun eveniment.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $events->links() }}
</x-layouts.crm>
