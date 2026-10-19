<x-layouts.crm :title="__('Cumpărători')">
    <x-slot:actions>
        @can('visitors.manage')<a href="{{ route('crm.visitors.create') }}" class="btn-primary">+ {{ __('Cumpărător nou') }}</a>@endcan
        @can('exports.run')
            <a href="{{ route('crm.visitors.export', request()->query()) }}" class="btn-ghost">CSV</a>
            <a href="{{ route('crm.visitors.export', request()->query() + ['format' => 'xlsx']) }}" class="btn-ghost">XLSX</a>
        @endcan
    </x-slot:actions>
    @php $filters = ['all' => __('TOȚI').' ('.$counts['all'].')', 'ro' => 'RO ('.$counts['ro'].')', 'hu' => 'HU ('.$counts['hu'].')', '5plus' => __('5+ VIZITE'), '10plus' => __('10+ VIZITE'), 'new' => __('NOI'), 'returning' => __('RECURENȚI')]; @endphp
    <form class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($filters as $k => $label)
            <a href="{{ route('crm.visitors.index', array_filter(['filter' => $k, 'q' => request('q'), 'sort' => request('sort')])) }}" @class(['chip', 'chip-active' => $filter === $k])>{{ $label }}</a>
        @endforeach
        <input type="hidden" name="filter" value="{{ $filter }}">
        <select name="sort" onchange="this.form.submit()" class="field-input !w-auto !py-1.5">
            <option value="recent" @selected($sort === 'recent')>{{ __('Cei mai noi') }}</option>
            <option value="visits" @selected($sort === 'visits')>{{ __('Cele mai multe vizite') }}</option>
            <option value="name" @selected($sort === 'name')>{{ __('Nume') }}</option>
        </select>
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Nume, e-mail, telefon, cod…') }}" class="field-input !w-64 !py-1.5">
    </form>
    <div class="card overflow-x-auto">
        <table class="table-crm min-w-[1000px]">
            <thead><tr><th>{{ __('Nume') }}</th><th>E-mail</th><th>{{ __('Telefon') }}</th><th>{{ __('Localitate') }}</th><th>{{ __('Limbă') }}</th><th class="text-right">{{ __('Vizite') }}</th><th>{{ __('Ultima vizită') }}</th><th class="text-right">{{ __('Recomp.') }}</th><th class="text-right">{{ __('Tombolă') }}</th><th class="text-right">{{ __('Recenzii') }}</th><th>{{ __('Înscris') }}</th></tr></thead>
            <tbody>
            @forelse ($visitors as $v)
                <tr>
                    <td><a href="{{ route('crm.visitors.show', $v) }}" class="font-medium hover:text-clay">{{ $v->name }}</a><p class="font-mono text-xs text-ink-soft">{{ $v->member_code }}</p></td>
                    <td class="text-sm">{{ $v->email }}</td>
                    <td class="text-sm">{{ $v->phone }}</td>
                    <td class="text-sm">{{ $v->city }}</td>
                    <td><x-lang-badge :lang="$v->preferred_language" /></td>
                    <td class="text-right font-semibold">{{ $v->checkins_count }}</td>
                    <td class="whitespace-nowrap text-xs">{{ $v->last_visit ? \Illuminate\Support\Carbon::parse($v->last_visit)->format('d.m.Y') : '—' }}</td>
                    <td class="text-right">{{ $v->rewards_count }}</td>
                    <td class="text-right">{{ $v->raffle_entries_count }}</td>
                    <td class="text-right">{{ $v->reviews_count }}</td>
                    <td class="whitespace-nowrap text-xs">{{ $v->created_at->format('d.m.Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-ink-soft">{{ __('Niciun vizitator.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $visitors->links() }}
</x-layouts.crm>
