<x-layouts.crm :title="__('Template-uri documente')">
    <p class="mb-6 max-w-3xl text-sm text-ink-soft">{{ __('Contractul și anexa se generează în limba producătorului (RO/HU). Textele juridice marcate „[DE COMPLETAT]” trebuie redactate de jurist.') }}</p>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Nume') }}</th><th>{{ __('Tip') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Versiune') }}</th><th>{{ __('Activ') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($templates as $t)
                <tr><td>{{ $t->name }}</td><td>{{ $t->type === 'contract' ? __('Contract') : __('Anexă') }}</td><td><x-lang-badge :lang="$t->language" /></td><td>v{{ $t->version }}</td><td>{{ $t->is_active ? '✓' : '—' }}</td>
                    <td class="text-right"><a href="{{ route('crm.templates.preview', $t) }}" target="_blank" class="btn-ghost btn-sm">{{ __('Previzualizare') }}</a> <a href="{{ route('crm.templates.edit', $t) }}" class="btn-primary btn-sm">{{ __('Editează') }}</a></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.crm>
