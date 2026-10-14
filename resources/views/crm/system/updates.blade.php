<x-layouts.crm :title="__('Sistem → Actualizări')">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-[var(--radius-card)] bg-forest-dark p-6 text-cream">
            <p class="eyebrow !text-honey">{{ __('Versiune instalată') }}</p>
            <p class="mt-2 font-display text-6xl">{{ $version }}</p>
            <p class="mt-3 text-sm text-cream/60">{{ __('Actualizările se instalează exclusiv prin pachete ZIP generate de echipa de dezvoltare.') }}</p>
        </div>
        <div class="card card-pad lg:col-span-2">
            @if ($pending)
                <p class="eyebrow">{{ __('Pachet pregătit') }}</p>
                <p class="mt-2 font-display text-2xl">Autentik Market {{ $pending->version }} <x-status :status="$pending->status" /></p>
                <a href="{{ route('crm.updates.show', $pending) }}" class="btn-accent mt-4">{{ __('Continuă instalarea') }} →</a>
            @else
                <h2 class="text-xl">{{ __('ÎNCARCĂ ACTUALIZARE') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Selectează fișierul autentikmarket-update-X.Y.Z.zip. Pachetul este verificat (structură, manifest, versiune, compatibilitate, SHA-256, semnătură) înainte de orice modificare.') }}</p>
                <form method="POST" action="{{ route('crm.updates.upload') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3" x-data="{ busy: false }" @submit="busy = true">@csrf
                    <input type="file" name="package" accept=".zip,application/zip" required class="block text-sm">
                    <button class="btn-primary" :disabled="busy"><span x-show="!busy">{{ __('Încarcă și verifică') }}</span><span x-show="busy" x-cloak>{{ __('Se verifică…') }}</span></button>
                </form>
                <p class="mt-2 text-xs text-ink-soft">{{ __('Limita de upload a serverului') }}: {{ round($maxUpload / 1048576) }} MB</p>
                @error('package')<p class="field-error">{{ $message }}</p>@enderror
            @endif
        </div>
    </div>

    <h2 class="mt-10 text-2xl">{{ __('Istoric') }}</h2>
    <div class="card mt-4 overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Versiune') }}</th><th>{{ __('Anterioară') }}</th><th>{{ __('Data') }}</th><th>{{ __('Administrator') }}</th><th>{{ __('Status') }}</th><th>{{ __('Durată') }}</th><th>Backup</th><th>{{ __('Mesaj') }}</th></tr></thead>
            <tbody>
            @forelse ($history as $u)
                <tr>
                    <td class="font-semibold"><a href="{{ route('crm.updates.show', $u) }}" class="hover:text-clay">{{ $u->version ?? '—' }}</a></td>
                    <td class="text-sm">{{ $u->previous_version }}</td>
                    <td class="whitespace-nowrap text-xs">{{ ($u->finished_at ?? $u->created_at)->format('d.m.Y H:i') }}</td>
                    <td class="text-sm">{{ ($u->installer ?? $u->uploader)?->name ?? 'CLI' }}</td>
                    <td><x-status :status="$u->status" /></td>
                    <td class="text-xs">{{ $u->duration_ms ? round($u->duration_ms / 1000, 1).' s' : '—' }}</td>
                    <td class="text-xs">{{ $u->backup ? ($u->backup->database_included ? __('fișiere + DB') : __('fișiere')) : '—' }}</td>
                    <td class="max-w-xs truncate text-xs" title="{{ $u->message }}">{{ $u->message }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-ink-soft">{{ __('Nicio actualizare încă.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $history->links() }}
</x-layouts.crm>
