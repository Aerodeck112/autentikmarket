<x-layouts.crm :title="__('Tombole')">
    <x-slot:actions><a href="{{ route('crm.raffles.create') }}" class="btn-primary">+ {{ __('Tombolă nouă') }}</a></x-slot:actions>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Tombolă') }}</th><th>{{ __('Eveniment') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Înscriși') }}</th><th>{{ __('Extragere') }}</th></tr></thead>
            <tbody>
            @forelse ($raffles as $r)
                <tr>
                    <td><a href="{{ route('crm.raffles.show', $r) }}" class="font-medium hover:text-clay">{{ $r->name_ro }}</a><p class="text-xs text-ink-soft">{{ $r->name_hu }}</p></td>
                    <td class="text-sm">{{ $r->event->title_ro }} · {{ $r->event->event_date->format('d.m.Y') }}</td>
                    <td><x-status :status="$r->status" /></td>
                    <td class="text-right">{{ $r->entries_count }}</td>
                    <td class="text-xs">{{ $r->currentDraw ? $r->currentDraw->performed_at->format('d.m.Y H:i') : ($r->draw_at?->format('d.m.Y H:i') ?? '—') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-soft">{{ __('Nicio tombolă.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $raffles->links() }}
</x-layouts.crm>
