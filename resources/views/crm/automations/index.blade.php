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
            <div id="queue" class="card card-pad space-y-3 text-sm">
                <h2 class="text-xl">{{ __('Coada de e-mailuri') }}</h2>
                <p>{{ __('Joburi în coadă') }}: <strong>{{ $queue }}</strong> · {{ __('Joburi eșuate') }}: <strong @class(['text-red-700' => $failedJobs])>{{ $failedJobs }}</strong></p>
                <p class="text-xs text-ink-soft">
                    {{ __('Ultimul cron') }}: <strong>{{ $heartbeat ?: __('niciodată') }}</strong>@if ($cronInfo) · PHP {{ $cronInfo['php'] }}@endif
                    <br>{{ __('Ultima procesare a cozii') }}:
                    @if ($lastRun)<strong>{{ $lastRun['at'] }}</strong> ({{ $lastRun['source'] === 'crm' ? __('manual') : 'cron' }}) · {{ __('procesate') }} {{ $lastRun['processed'] }} · {{ __('rămase') }} {{ $lastRun['remaining'] }}@if ($lastRun['error'])<br><span class="text-red-700">{{ $lastRun['error'] }}</span>@endif
                    @else<strong>{{ __('niciodată') }}</strong>@endif
                </p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('crm.automations.queue.run') }}">@csrf<button class="btn-accent btn-sm">✉️ {{ __('Trimite acum mesajele din coadă') }}</button></form>
                    @if ($failedJobs)<form method="POST" action="{{ route('crm.automations.queue.retry') }}">@csrf<button class="btn-ghost btn-sm">↻ {{ __('Reîncearcă joburile eșuate') }}</button></form>@endif
                </div>
                @if (! $heartbeat)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                        <p class="font-semibold">{{ __('Cron-ul nu rulează. Adaugă în cPanel → Cron Jobs (Once Per Minute) comanda:') }}</p>
                        <code class="mt-2 block break-all rounded bg-white/70 p-2 font-mono text-[11px]">{{ $cronCommand }}</code>
                        <p class="mt-2">{{ __('Dacă tot nu merge, PHP-ul din cron are altă versiune decât site-ul (trebuie 8.3+): calea corectă apare în cPanel → MultiPHP Manager.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.crm>
