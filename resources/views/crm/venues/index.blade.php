<x-layouts.crm :title="__('Hartă & standuri')">
    <x-slot:actions><a href="{{ route('crm.venues.create') }}" class="btn-primary">+ {{ __('Locație nouă') }}</a></x-slot:actions>
    <p class="mb-6 max-w-3xl text-sm text-ink-soft">{{ __('Fiecare locație are un layout MASTER, editabil. La crearea unui târg, layout-ul master este copiat (snapshot) — modificările ulterioare nu afectează evenimentele existente.') }}</p>
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($venues as $venue)
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="eyebrow">{{ $venue->code }} · {{ $venue->city }}</p>
                        <h2 class="mt-1 text-2xl">{{ $venue->name }}</h2>
                        <p class="text-sm text-ink-soft">{{ $venue->address }} · {{ $venue->events_count }} {{ __('evenimente') }}</p>
                    </div>
                    <a href="{{ route('crm.venues.edit', $venue) }}" class="btn-ghost btn-sm">{{ __('Editează') }}</a>
                </div>
                <ul class="mt-4 divide-y divide-line text-sm">
                    @foreach ($venue->layouts as $layout)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>@if ($layout->is_master)<span class="badge bg-forest text-cream">MASTER</span>@endif {{ $layout->name }} <span class="text-ink-soft">· {{ $layout->slots_count }} {{ __('standuri') }} · v{{ $layout->version }}</span></span>
                            <a href="{{ route('crm.layouts.editor', $layout) }}" class="btn-primary btn-sm">{{ __('Editor') }}</a>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('crm.layouts.store', $venue) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-line pt-3">@csrf
                    <input name="name" placeholder="{{ __('Nume layout nou') }}" required class="field-input !w-auto flex-1">
                    <input name="width_m" type="number" step="0.5" value="40" class="field-input !w-20" title="{{ __('lățime (m)') }}">
                    <input name="height_m" type="number" step="0.5" value="30" class="field-input !w-20" title="{{ __('înălțime (m)') }}">
                    <button class="btn-ghost btn-sm">+ Layout</button>
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.crm>
