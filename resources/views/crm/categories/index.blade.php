<x-layouts.crm :title="__('Categorii producători')">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <table class="table-crm">
                <thead><tr><th></th><th>RO</th><th>HU</th><th>{{ __('Producători') }}</th><th>{{ __('Activă') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($categories as $c)
                    <tr>
                        <td>{{ $c->icon }}</td><td>{{ $c->name_ro }}</td><td>{{ $c->name_hu }}</td><td>{{ $c->producers_count }}</td><td>{{ $c->active ? '✓' : '—' }}</td>
                        <td class="text-right"><a href="{{ route('crm.categories.edit', $c) }}" class="text-sm text-clay">{{ __('Editează') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ $editing ? route('crm.categories.update', $editing) : route('crm.categories.store') }}" class="card card-pad space-y-3">
            @csrf @if ($editing) @method('PUT') @endif
            <h2 class="text-xl">{{ $editing ? __('Editează categoria') : __('Categorie nouă') }}</h2>
            <x-input name="name_ro" :label="__('Nume RO')" :value="$editing?->name_ro" required />
            <x-input name="name_hu" :label="__('Nume HU')" :value="$editing?->name_hu" required />
            <x-input name="icon" :label="__('Icon (emoji)')" :value="$editing?->icon" />
            <x-input name="sort_order" type="number" :label="__('Ordine')" :value="$editing?->sort_order" />
            <x-checkbox name="active" :label="__('Activă')" :checked="$editing?->active ?? true" />
            <button class="btn-primary">{{ __('Salvează') }}</button>
        </form>
    </div>
    @if ($editing)
        <form method="POST" action="{{ route('crm.categories.destroy', $editing) }}" class="mt-4 text-right" onsubmit="return confirm('{{ __('Ștergi categoria?') }}')">@csrf @method('DELETE')<button class="text-sm text-red-700">{{ __('Șterge categoria') }}</button></form>
    @endif
</x-layouts.crm>
