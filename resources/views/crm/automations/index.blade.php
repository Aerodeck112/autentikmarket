<x-layouts.crm :title="__('Automatizări')">
    <x-slot:actions><a href="{{ route('crm.automations.log') }}" class="btn-ghost">{{ __('Jurnal notificări') }}</a></x-slot:actions>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach (\App\Enums\NotificationStatus::cases() as $st)
            <x-stat :label="$st->label().' (30 '.__('zile').')'" :value="$stats[$st->value] ?? 0" :href="route('crm.automations.log', ['status' => $st->value])" :tone="$st->value === 'failed' && ($stats['failed'] ?? 0) ? 'alert' : 'default'" />
        @endforeach
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card overflow-x-auto xl:col-span-2">
            <div class="p-5 pb-0"><h2 class="text-xl">{{ __('Template-uri notificări (RO/HU)') }}</h2><p class="text-sm text-ink-soft">{{ __('Limba fiecărui mesaj este aleasă automat după destinatar.') }}</p></div>
            <table class="table-crm mt-3">
                <thead><tr><th>Key</th><th>{{ __('Descriere') }}</th><th>{{ __('Canal') }}</th><th>{{ __('Activ') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($templates as $t)
                    <tr><td class="font-mono text-xs">{{ $t->key }}</td><td class="text-sm">{{ $t->description }}</td><td class="text-xs">{{ $t->channel }}</td><td>{{ $t->active ? '✓' : '—' }}</td><td class="text-right"><a href="{{ route('crm.automations.edit', $t) }}" class="btn-ghost btn-sm">{{ __('Editează') }}</a></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Scheduler (cPanel Cron)') }}</h2>
                <p class="mt-1 text-xs text-ink-soft">{{ __('Un singur cron: php artisan schedule:run, în fiecare minut.') }}</p>
                <ul class="mt-3 space-y-2 text-sm">@foreach ($schedule as [$cmd, $when, $desc])<li><span class="font-mono text-xs">{{ $cmd }}</span> <span class="text-xs text-ink-soft">· {{ $when }}</span><br><span class="text-ink-soft">{{ $desc }}</span></li>@endforeach</ul>
            </div>
            <div class="card card-pad text-sm">
                <p>{{ __('Joburi în coadă') }}: <strong>{{ $queue }}</strong></p>
                <p>{{ __('Joburi eșuate') }}: <strong @class(['text-red-700' => $failedJobs])>{{ $failedJobs }}</strong></p>
            </div>
        </div>
    </div>
</x-layouts.crm>
