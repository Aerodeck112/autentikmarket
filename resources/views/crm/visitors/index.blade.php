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
        <select name="event" onchange="this.form.submit()" class="field-input !w-auto !py-1.5">
            <option value="">{{ __('Toate edițiile') }}</option>
            @foreach ($events as $id => $label)<option value="{{ $id }}" @selected((int) request('event') === $id)>{{ $label }}</option>@endforeach
        </select>
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Nume, e-mail, telefon, cod…') }}" class="field-input !w-64 !py-1.5">
    </form>
    <div class="card overflow-x-auto">
        <table class="table-crm min-w-[1000px]">
            <thead><tr><th>{{ __('Nume') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Limbă') }}</th><th class="text-right">{{ __('Vizite') }}</th><th>{{ __('Ediții') }}</th><th>{{ __('A cumpărat de la') }}</th><th class="text-right">{{ __('Recomp.') }}</th><th>{{ __('Înscris') }}</th></tr></thead>
            <tbody>
            @forelse ($visitors as $v)
                @php
                    $tags = $v->checkins->map(fn ($c) => \App\Http\Controllers\Crm\VisitorController::editionTag($c->event))->filter()->values();
                    $bought = $v->interactions->pluck('producer.brand_name')->filter()->unique()->sort()->values();
                @endphp
                <tr>
                    <td><a href="{{ route('crm.visitors.show', $v) }}" class="font-medium hover:text-clay">{{ $v->name }}</a><p class="font-mono text-xs text-ink-soft">{{ $v->member_code }}@if ($v->city) · {{ $v->city }}@endif</p></td>
                    <td class="text-sm">{{ $v->email ?: '—' }}<p class="text-xs text-ink-soft">{{ $v->phone }}</p></td>
                    <td><x-lang-badge :lang="$v->preferred_language" /></td>
                    <td class="text-right font-semibold">{{ $v->checkins_count }}</td>
                    <td class="text-xs">
                        @forelse ($tags->take(-4) as $tag)<span class="mb-1 mr-1 inline-block whitespace-nowrap rounded-full bg-sand px-2 py-0.5">{{ $tag }}</span>@empty<span class="text-ink-soft">—</span>@endforelse
                        @if ($tags->count() > 4)<span class="text-ink-soft">+{{ $tags->count() - 4 }}</span>@endif
                    </td>
                    <td class="text-xs">{{ $bought->take(3)->implode(', ') ?: '—' }}@if ($bought->count() > 3)<span class="text-ink-soft"> +{{ $bought->count() - 3 }}</span>@endif</td>
                    <td class="text-right">{{ $v->rewards_count }}</td>
                    <td class="whitespace-nowrap text-xs">{{ $v->created_at->format('d.m.Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-ink-soft">{{ __('Niciun cumpărător.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $visitors->links() }}
</x-layouts.crm>
