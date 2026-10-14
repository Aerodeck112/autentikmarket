<x-layouts.crm :title="__('Contracte')">
    <x-slot:actions><a href="{{ route('crm.templates.index') }}" class="btn-ghost">{{ __('Template-uri') }}</a></x-slot:actions>
    <form class="mb-4"><input name="q" value="{{ $q }}" placeholder="{{ __('Număr sau producător…') }}" class="field-input !w-72"></form>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Număr') }}</th><th>{{ __('Producător') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Generat') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($contracts as $c)
                <tr>
                    <td class="font-mono text-sm"><a href="{{ route('files.contract', $c) }}" target="_blank" class="text-clay hover:underline">{{ $c->number }}</a></td>
                    <td><a href="{{ route('crm.producers.show', $c->producer) }}" class="hover:text-clay">{{ $c->producer->brand_name }}</a></td>
                    <td><x-lang-badge :lang="$c->language" /></td>
                    <td class="text-xs">{{ $c->generated_at->format('d.m.Y') }} · {{ $c->generator?->name }}</td>
                    <td><x-status :status="$c->status" /></td>
                    <td>
                        <form method="POST" action="{{ route('crm.contracts.status', $c) }}" enctype="multipart/form-data" class="flex items-center gap-1">@csrf
                            <select name="status" class="field-input !w-auto !py-1 text-xs">@foreach (['generated' => __('Generat'), 'sent' => __('Trimis'), 'signed' => __('Semnat'), 'cancelled' => __('Anulat')] as $k => $l)<option value="{{ $k }}" @selected($c->status === $k)>{{ $l }}</option>@endforeach</select>
                            <input type="file" name="signed_file" accept="application/pdf" class="w-36 text-xs" title="{{ __('PDF semnat') }}">
                            <button class="btn-ghost btn-sm">OK</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-ink-soft">{{ __('Niciun contract. Contractele se generează din fișa producătorului aprobat.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $contracts->links() }}

    <h2 class="mt-10 text-2xl">{{ __('Anexe eveniment') }}</h2>
    <div class="card mt-4 overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Număr') }}</th><th>{{ __('Producător') }}</th><th>{{ __('Eveniment') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Generat') }}</th></tr></thead>
            <tbody>
            @forelse ($annexes as $a)
                <tr><td class="font-mono text-sm"><a href="{{ route('files.annex', $a) }}" target="_blank" class="text-clay hover:underline">{{ $a->number }}</a></td><td>{{ $a->producer->brand_name }}</td><td class="text-sm">{{ $a->event->title_ro }} · {{ $a->event->event_date->format('d.m.Y') }}</td><td><x-lang-badge :lang="$a->language" /></td><td class="text-xs">{{ $a->generated_at->format('d.m.Y H:i') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-soft">—</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $annexes->links() }}
</x-layouts.crm>
