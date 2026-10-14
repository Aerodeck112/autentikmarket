<x-layouts.crm :title="__('Sistem')">
    <x-slot:actions>
        @can('system.update')<a href="{{ route('crm.updates.index') }}" class="btn-primary">{{ __('Actualizări') }}</a>@endcan
        @can('audit.view')<a href="{{ route('crm.system.audit') }}" class="btn-ghost">{{ __('Audit log') }}</a>@endcan
    </x-slot:actions>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-[var(--radius-card)] bg-forest-dark p-6 text-cream">
            <p class="eyebrow !text-honey">{{ __('Versiune') }}</p>
            <p class="mt-2 font-display text-6xl">{{ $version }}</p>
            @if ($lastUpdate)<p class="mt-2 text-sm text-cream/60">{{ __('Ultima actualizare') }}: {{ $lastUpdate->finished_at?->format('d.m.Y H:i') }}</p>@endif
        </div>
        <div class="card card-pad lg:col-span-2">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                @foreach ($checks as $k => $v)<div class="flex justify-between border-b border-line pb-2"><dt class="text-ink-soft">{{ $k }}</dt><dd class="font-mono">{{ $v }}</dd></div>@endforeach
            </dl>
        </div>
    </div>
</x-layouts.crm>
