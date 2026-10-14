<x-layouts.app :title="__('Mesaje')">
    <h1 class="text-3xl sm:text-4xl">{{ __('Mesaje primite') }}</h1>
    <div class="card mt-8 divide-y divide-line">
        @forelse ($logs as $log)
            <details class="group p-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                    <span class="font-medium">{{ $log->subject }}</span>
                    <span class="flex items-center gap-2 text-xs text-ink-soft"><x-lang-badge :lang="$log->language" /> {{ $log->created_at->format('d.m.Y H:i') }}</span>
                </summary>
                <iframe srcdoc="{{ $log->body }}" sandbox class="mt-4 h-[420px] w-full rounded-xl border border-line bg-white"></iframe>
            </details>
        @empty
            <p class="p-6 text-sm text-ink-soft">{{ __('Nu ai mesaje.') }}</p>
        @endforelse
    </div>
    {{ $logs->links() }}
</x-layouts.app>
