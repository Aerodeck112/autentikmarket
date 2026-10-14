<x-layouts.crm :title="__('Producători')">
    <x-slot:actions>
        @can('producers.manage')<a href="{{ route('crm.categories.index') }}" class="btn-ghost">{{ __('Categorii') }}</a>@endcan
        @can('exports.run')
            <a href="{{ route('crm.producers.export', request()->query()) }}" class="btn-ghost">CSV</a>
            <a href="{{ route('crm.producers.export', request()->query() + ['format' => 'xlsx']) }}" class="btn-ghost">XLSX</a>
        @endcan
    </x-slot:actions>
    @php $filters = ['all' => __('TOȚI').' ('.$counts['all'].')', 'ro' => __('ROMÂNĂ').' ('.$counts['ro'].')', 'hu' => __('MAGHIARĂ').' ('.$counts['hu'].')', 'review' => __('ÎN VERIFICARE').' ('.$counts['review'].')', 'approved' => __('APROBAȚI').' ('.$counts['approved'].')', 'missing_docs' => __('DOCUMENTE LIPSĂ'), 'new' => __('NOI')]; @endphp
    <form class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($filters as $k => $label)
            <a href="{{ route('crm.producers.index', array_filter(['filter' => $k, 'q' => request('q'), 'category' => request('category')])) }}" @class(['chip', 'chip-active' => $filter === $k])>{{ $label }}</a>
        @endforeach
        <input type="hidden" name="filter" value="{{ $filter }}">
        <select name="category" onchange="this.form.submit()" class="field-input !w-auto !py-1.5"><option value="">{{ __('Toate categoriile') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name_ro }}</option>@endforeach</select>
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Caută…') }}" class="field-input !w-56 !py-1.5">
    </form>
    <div class="card overflow-x-auto">
        <table class="table-crm min-w-[1100px]">
            <thead><tr>
                <th>{{ __('Producător') }}</th><th>{{ __('Reprezentant') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Localitate') }}</th><th>{{ __('Limbă') }}</th>
                <th>{{ __('Categorie') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Particip.') }}</th><th>{{ __('Ultima') }}</th><th>{{ __('Următorul târg') }}</th>
                <th class="text-center">{{ __('Contract') }}</th><th class="text-center">{{ __('Doc.') }}</th><th class="text-right">{{ __('Rating') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($producers as $p)
                <tr>
                    <td><a href="{{ route('crm.producers.show', $p) }}" class="font-medium hover:text-clay">{{ $p->brand_name }}</a></td>
                    <td class="text-sm">{{ $p->representative_name }}</td>
                    <td class="text-xs">{{ $p->phone }}<br><span class="text-ink-soft">{{ $p->email }}</span></td>
                    <td class="text-sm">{{ $p->city }}</td>
                    <td><x-lang-badge :lang="$p->preferred_language" /></td>
                    <td class="text-sm">{{ $p->category?->name_ro }}</td>
                    <td><x-status :status="$p->status" /></td>
                    <td class="text-right">{{ $p->participations_count }}</td>
                    <td class="whitespace-nowrap text-xs">{{ $p->last_participation ? \Illuminate\Support\Carbon::parse($p->last_participation)->format('d.m.Y') : '—' }}</td>
                    <td class="text-xs">@foreach ($nextRegs->get($p->id, collect()) as $nr){{ $nr->event->event_date->format('d.m') }} <x-status :status="$nr->status" />@endforeach</td>
                    <td class="text-center">{{ $p->contracts_count ? '✓' : '—' }}</td>
                    <td class="text-center">{{ $p->documents_count }}</td>
                    <td class="text-right">{{ $p->rating ? '★ '.number_format($p->rating, 1) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="text-center text-ink-soft">{{ __('Niciun producător.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $producers->links() }}
</x-layouts.crm>
