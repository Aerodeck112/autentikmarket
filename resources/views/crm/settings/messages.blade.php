<x-layouts.crm :title="__('Mesaje contact')">
    <div class="space-y-3">
        @forelse ($messages as $m)
            <div @class(['card card-pad', 'opacity-60' => $m->handled_at])>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium">{{ $m->name }} · <a href="mailto:{{ $m->email }}" class="text-clay">{{ $m->email }}</a> {{ $m->phone }} <x-lang-badge :lang="$m->language" /></p>
                    <span class="text-xs text-ink-soft">{{ $m->created_at->format('d.m.Y H:i') }}</span>
                </div>
                @if ($m->subject)<p class="mt-1 text-sm font-semibold">{{ $m->subject }}</p>@endif
                <p class="mt-2 whitespace-pre-line text-sm">{{ $m->message }}</p>
                @unless ($m->handled_at)<form method="POST" action="{{ route('crm.messages.handled', $m) }}" class="mt-3">@csrf<button class="btn-ghost btn-sm">✓ {{ __('Marchează rezolvat') }}</button></form>@endunless
            </div>
        @empty
            <x-empty :title="__('Niciun mesaj')" />
        @endforelse
    </div>
    {{ $messages->links() }}
</x-layouts.crm>
