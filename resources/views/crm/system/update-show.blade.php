<x-layouts.crm :title="'Autentik Market '.($update->version ?? '—')">
    <x-slot:breadcrumb><a href="{{ route('crm.updates.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Actualizări') }}</a></x-slot:breadcrumb>
    @php
        $m = $update->manifest ?? [];
        $stepLabels = ['preflight' => __('Pre-flight check'), 'backup' => __('Backup (fișiere + bază de date)'), 'maintenance' => __('Maintenance mode'), 'install' => __('Instalare fișiere'), 'migrate' => __('Migrații bază de date'), 'optimize' => __('Curățare și reconstruire cache'), 'verify' => __('Test de bază'), 'finalize' => __('Online — versiune nouă')];
        $steps = \App\Services\Updates\UpdateManager::STEPS;
        $doneIdx = $update->current_step ? array_search($update->current_step, $steps, true) : -1;
        $completed = array_slice($steps, 0, $doneIdx + 1);
        $destructive = $update->destructive_migrations;
    @endphp
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center gap-3">
                    <x-status :status="$update->status" />
                    <span class="text-sm text-ink-soft">{{ $update->previous_version }} → <strong>{{ $update->version }}</strong></span>
                    @if (! empty($m['minimum_version']))<span class="text-xs text-ink-soft">{{ __('necesită') }} ≥ {{ $m['minimum_version'] }}</span>@endif
                    @if (! empty($m['release_date']))<span class="text-xs text-ink-soft">· {{ $m['release_date'] }}</span>@endif
                    <span class="text-xs">{{ $update->signature_valid === true ? '🔏 '.__('semnătură validă') : ($update->signature_valid === false ? '⚠ '.__('semnătură invalidă') : __('nesemnat')) }}</span>
                </div>
                <h2 class="mt-4 text-3xl">AUTENTIK MARKET {{ $update->version }}</h2>
                @php $desc = $m['description'][app()->getLocale()] ?? ($m['description']['ro'] ?? null); @endphp
                @if ($desc)<p class="mt-2 text-ink-soft">{{ $desc }}</p>@endif
                @if ($update->release_notes)
                    <div class="prose-am mt-4 rounded-xl bg-cream/60 p-4 text-sm">{!! nl2br(e($update->release_notes)) !!}</div>
                @endif
                <p class="mt-3 text-xs text-ink-soft">{{ count($m['files'] ?? []) }} {{ __('fișiere') }} · {{ count($m['delete'] ?? []) }} {{ __('ștergeri') }} · {{ __('migrații') }}: {{ ($m['database_migration_required'] ?? false) ? __('DA') : __('NU') }} · vendor: {{ ($m['includes_vendor'] ?? false) ? __('DA') : __('NU') }} · SHA-256 {{ Str::limit($update->package_sha256, 16, '…') }}</p>
            </div>

            @if ($destructive && empty($destructive['confirmed_by']) && $next)
                <form method="POST" action="{{ route('crm.updates.confirm', $update) }}" class="rounded-[var(--radius-card)] border-2 border-red-300 bg-red-50 p-5 text-red-900">@csrf
                    <h3 class="font-display text-xl">⚠ {{ __('Migrații potențial distructive') }}</h3>
                    <ul class="mt-2 list-disc pl-5 text-sm">@foreach ($destructive['files'] as $f)<li><span class="font-mono">{{ $f['file'] }}</span> — {{ implode(', ', $f['matches']) }}</li>@endforeach</ul>
                    <p class="mt-2 text-sm">{{ __('Verifică notele de release. Backup-ul bazei de date se creează automat înainte de instalare.') }}</p>
                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="confirm" value="1" required> {{ __('Am verificat și confirm instalarea acestor migrații.') }}</label>
                    <button class="btn-danger btn-sm mt-3">{{ __('Confirm') }}</button>
                </form>
            @endif

            @if ($next && (! $destructive || ! empty($destructive['confirmed_by'])))
                <div class="card card-pad" x-data="updateRunner({
                        steps: @js($steps), completed: @js($completed), next: @js($next),
                        stepUrl: '{{ route('crm.updates.step', [$update, '__STEP__']) }}',
                        labels: { confirm: @js(__('Instalezi actualizarea? Aplicația va intra câteva momente în maintenance mode.')), success: @js(__('Actualizarea a fost instalată cu succes.')), httpError: @js(__('eroare de comunicare cu serverul')) }
                     })">
                    <ol class="space-y-2">
                        @foreach ($steps as $i => $s)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="grid size-7 place-items-center rounded-full text-xs font-bold"
                                      :class="{ 'bg-forest text-cream': statuses['{{ $s }}'] === 'done', 'bg-honey text-ink animate-pulse': statuses['{{ $s }}'] === 'running', 'bg-red-600 text-white': statuses['{{ $s }}'] === 'failed', 'bg-sand text-ink-soft': statuses['{{ $s }}'] === 'pending' }"
                                      x-text="statuses['{{ $s }}'] === 'done' ? '✓' : (statuses['{{ $s }}'] === 'failed' ? '✕' : '{{ $i + 1 }}')"></span>
                                <span>{{ $stepLabels[$s] }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <button type="button" class="btn-accent btn-lg mt-6" @click="run()" :disabled="running || done" x-show="!done && !failed">{{ __('INSTALARE ACTUALIZARE') }}</button>
                    <p x-show="done" x-cloak class="mt-6 rounded-xl bg-emerald-50 p-4 font-semibold text-emerald-900" x-text="message"></p>
                    <p x-show="failed" x-cloak class="mt-6 rounded-xl bg-red-50 p-4 text-sm text-red-900" x-text="message"></p>
                    <ul class="mt-4 space-y-1 font-mono text-xs"><template x-for="l in log"><li :class="l.ok ? 'text-emerald-800' : 'text-red-700'" x-text="l.step + ': ' + l.message"></li></template></ul>
                </div>
            @elseif ($update->status->value === 'completed')
                <div class="rounded-[var(--radius-card)] bg-emerald-50 p-6 text-emerald-900"><p class="font-display text-2xl">✓ {{ __('Actualizarea a fost instalată cu succes.') }}</p></div>
            @elseif (in_array($update->status->value, ['failed', 'rejected', 'rolled_back']))
                <div class="rounded-[var(--radius-card)] bg-red-50 p-6 text-red-900"><p class="font-semibold">{{ $update->message }}</p></div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card card-pad">
                <h3 class="font-display text-lg">{{ __('Jurnal') }}</h3>
                <ol class="mt-3 space-y-2 text-xs">
                    @foreach (array_reverse($update->log ?? []) as $l)
                        <li @class(['text-red-700' => $l['level'] === 'error', 'text-amber-700' => $l['level'] === 'warning'])><span class="text-ink-soft">{{ \Illuminate\Support\Carbon::parse($l['at'])->format('H:i:s') }} · {{ $l['step'] }}</span><br>{{ $l['message'] }}</li>
                    @endforeach
                </ol>
            </div>
            @if ($update->backup)
                <div class="card card-pad text-sm">
                    <h3 class="font-display text-lg">Backup</h3>
                    <p class="mt-2 break-all font-mono text-xs text-ink-soft">{{ str_replace(storage_path(), 'storage', $update->backup->directory) }}</p>
                    <p class="mt-1 text-xs">{{ round($update->backup->size_bytes / 1048576, 1) }} MB · {{ $update->backup->database_included ? __('fișiere + DB') : __('fișiere') }}</p>
                    @if (in_array($update->status->value, ['failed', 'completed']) && $update->backup->files_archive)
                        <form method="POST" action="{{ route('crm.updates.rollback', $update) }}" class="mt-3" onsubmit="return confirm('{{ __('Restaurezi fișierele versiunii anterioare? Baza de date nu este modificată.') }}')">@csrf<button class="btn-ghost btn-sm">↶ {{ __('Rollback fișiere') }}</button></form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.crm>
