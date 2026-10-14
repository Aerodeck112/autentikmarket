<x-layouts.crm :title="__('Audit log')">
    <form class="mb-4"><select name="action" onchange="this.form.submit()" class="field-input !w-auto !py-1.5"><option value="">{{ __('Toate acțiunile') }}</option>@foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></form>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Data') }}</th><th>{{ __('Utilizator') }}</th><th>{{ __('Acțiune') }}</th><th>{{ __('Entitate') }}</th><th>{{ __('Valori vechi') }}</th><th>{{ __('Valori noi') }}</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="align-top">
                    <td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                    <td class="text-sm">{{ $log->user?->name ?? __('sistem') }}</td>
                    <td class="font-mono text-xs">{{ $log->action }}</td>
                    <td class="text-xs">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                    <td class="max-w-xs break-all font-mono text-[11px] text-ink-soft">{{ $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '' }}</td>
                    <td class="max-w-xs break-all font-mono text-[11px]">{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '' }}</td>
                    <td class="text-xs text-ink-soft">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-ink-soft">—</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layouts.crm>
