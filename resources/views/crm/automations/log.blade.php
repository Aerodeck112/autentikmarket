<x-layouts.crm :title="__('Jurnal notificări')">
    <x-slot:breadcrumb><a href="{{ route('crm.automations.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Automatizări') }}</a></x-slot:breadcrumb>
    <form class="mb-4 flex flex-wrap gap-2">
        <select name="status" class="field-input !w-auto !py-1.5"><option value="">{{ __('Toate statusurile') }}</option>@foreach (\App\Enums\NotificationStatus::options() as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
        <select name="key" class="field-input !w-auto !py-1.5"><option value="">{{ __('Toate template-urile') }}</option>@foreach ($keys as $k)<option @selected(request('key') === $k)>{{ $k }}</option>@endforeach</select>
        <select name="language" class="field-input !w-auto !py-1.5"><option value="">RO + HU</option><option value="ro" @selected(request('language') === 'ro')>RO</option><option value="hu" @selected(request('language') === 'hu')>HU</option></select>
        <button class="btn-ghost btn-sm">{{ __('Filtrează') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Data') }}</th><th>Key</th><th>{{ __('Destinatar') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Subiect') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                    <td class="font-mono text-xs">{{ $log->template_key }}</td>
                    <td class="text-xs">{{ $log->recipient_email }}<br><span class="text-ink-soft">{{ class_basename($log->notifiable_type) }} #{{ $log->notifiable_id }}</span></td>
                    <td><x-lang-badge :lang="$log->language" /></td>
                    <td class="text-sm">{{ Str::limit($log->subject, 60) }}</td>
                    <td><x-status :status="$log->status" />@if ($log->error)<p class="max-w-xs truncate text-xs text-red-700" title="{{ $log->error }}">{{ $log->error }}</p>@endif</td>
                    <td>@if ($log->status->value === 'failed')<form method="POST" action="{{ route('crm.automations.retry', $log) }}">@csrf<button class="btn-ghost btn-sm">{{ __('Retrimite') }}</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-ink-soft">—</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layouts.crm>
