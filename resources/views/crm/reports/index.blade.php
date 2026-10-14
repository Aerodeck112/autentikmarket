<x-layouts.crm :title="__('Rapoarte')">
    <x-slot:actions>
        @can('exports.run')
            <a href="{{ route('crm.reports.languages.export', array_filter(['city' => $city])) }}" class="btn-ghost">CSV</a>
            <a href="{{ route('crm.reports.languages.export', array_filter(['city' => $city, 'format' => 'xlsx'])) }}" class="btn-ghost">XLSX</a>
        @endcan
    </x-slot:actions>
    @php $d = $dashboard; @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Vizitatori total')" :value="$d['visitors_total']" :hint="'RO '.$d['visitors_ro_pct'].'% · HU '.$d['visitors_hu_pct'].'%'" />
        <x-stat :label="__('Producători aprobați')" :value="$d['producers_approved']" :hint="'RO '.$d['producers_ro_pct'].'% · HU '.$d['producers_hu_pct'].'%'" />
        <x-stat :label="__('Vizitatori recurenți')" :value="$d['visitors_returning']" />
        <x-stat :label="__('Ediții încheiate')" :value="$d['events_completed']" />
    </div>

    <form class="mt-8 flex items-center gap-2"><span class="text-sm text-ink-soft">{{ __('Oraș') }}:</span>
        <select name="city" onchange="this.form.submit()" class="field-input !w-auto !py-1.5"><option value="">{{ __('Toate') }}</option>@foreach ($cities as $c)<option @selected($city === $c)>{{ $c }}</option>@endforeach</select>
    </form>

    <div class="mt-4 space-y-6">
        @forelse ($rows as $r)
            <div class="card card-pad">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-2xl uppercase">{{ $r['event']->title_ro }}</h2>
                    <a href="{{ route('crm.events.show', $r['event']) }}" class="text-sm text-ink-soft hover:text-clay">{{ $r['event']->event_date->format('d.m.Y') }} →</a>
                </div>
                <div class="mt-4 grid gap-6 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-ink-soft">{{ __('Vizitatori') }}</p>
                        <p class="font-display text-3xl">{{ $r['visitors_total'] }} <span class="text-base text-ink-soft">{{ __('total') }}</span></p>
                        <p class="mt-1 text-sm"><x-lang-badge lang="ro" /> RO: <strong>{{ $r['visitors_ro'] }}</strong> ({{ $r['visitors_ro_pct'] }}%) · <x-lang-badge lang="hu" /> HU: <strong>{{ $r['visitors_hu'] }}</strong> ({{ $r['visitors_hu_pct'] }}%)</p>
                        <p class="text-xs text-ink-soft">{{ __('noi') }} {{ $r['visitors_new'] }} · {{ __('recurenți') }} {{ $r['visitors_returning'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-soft">{{ __('Producători') }}</p>
                        <p class="font-display text-3xl">{{ $r['producers_accepted'] }} <span class="text-base text-ink-soft">{{ __('total') }}</span></p>
                        <p class="mt-1 text-sm"><x-lang-badge lang="ro" /> RO: <strong>{{ $r['producers_ro'] }}</strong> · <x-lang-badge lang="hu" /> HU: <strong>{{ $r['producers_hu'] }}</strong></p>
                        <p class="text-xs text-ink-soft">{{ __('ocupare') }} {{ $r['occupancy_pct'] }}% · {{ __('interacțiuni') }} {{ $r['interactions'] }} · {{ __('recenzii') }} {{ $r['reviews'] }}@if ($r['rating']) (★{{ $r['rating'] }})@endif</p>
                    </div>
                </div>
            </div>
        @empty
            <x-empty :title="__('Încă nu există ediții încheiate')" :text="__('Rapoartele apar după primul târg încheiat.')" />
        @endforelse
    </div>
</x-layouts.crm>
