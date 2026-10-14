<x-layouts.crm :title="__('Căutare globală')">
    <form class="mb-6 flex max-w-xl gap-2"><input name="q" value="{{ $q }}" autofocus class="field-input" placeholder="{{ __('ex. Kovacs') }}"><button class="btn-primary">{{ __('Caută') }}</button></form>
    @if ($q && ! $results)
        <x-empty :title="__('Niciun rezultat')" :text="__('Încearcă un nume, un e-mail, un telefon, un cod de membru sau un număr de contract.')" />
    @endif
    @php $labels = ['visitors' => __('Vizitatori'), 'producers' => __('Producători'), 'contracts' => __('Contracte'), 'events' => __('Evenimente'), 'reservations' => __('Rezervări')]; @endphp
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($results as $type => $items)
            <div class="card card-pad">
                <h2 class="text-xl">{{ $labels[$type] }} <span class="text-ink-soft">({{ $items->count() }})</span></h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($items as $item)
                        <li class="py-2">
                            @switch($type)
                                @case('visitors')<a class="flex items-center justify-between hover:text-clay" href="{{ route('crm.visitors.show', $item) }}"><span>{{ $item->name }} · <span class="text-ink-soft">{{ $item->email }}</span></span><x-lang-badge :lang="$item->preferred_language" /></a>@break
                                @case('producers')<a class="flex items-center justify-between hover:text-clay" href="{{ route('crm.producers.show', $item) }}"><span>{{ $item->brand_name }} · <span class="text-ink-soft">{{ $item->city }}</span></span><span class="flex gap-1"><x-lang-badge :lang="$item->preferred_language" /><x-status :status="$item->status" /></span></a>@break
                                @case('contracts')<a class="hover:text-clay" href="{{ route('files.contract', $item) }}">{{ $item->number }} · {{ $item->producer->brand_name }}</a>@break
                                @case('events')<a class="hover:text-clay" href="{{ route('crm.events.show', $item) }}">{{ $item->title_ro }} · {{ $item->event_date->format('d.m.Y') }}</a>@break
                                @case('reservations')<a class="hover:text-clay" href="{{ route('crm.reservations.index', $item->event_id) }}">{{ $item->slot->code }} · {{ $item->producer->brand_name }} · {{ $item->event->title_ro }} <x-status :status="$item->status" /></a>@break
                            @endswitch
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-layouts.crm>
