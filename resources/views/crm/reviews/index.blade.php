<x-layouts.crm :title="__('Recenzii')">
    <div class="mb-4 flex gap-2">
        @foreach (\App\Enums\ReviewStatus::cases() as $st)
            <a href="{{ route('crm.reviews.index', ['status' => $st->value]) }}" @class(['chip', 'chip-active' => $status === $st->value])>{{ $st->label() }} ({{ $counts[$st->value] ?? 0 }})</a>
        @endforeach
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($reviews as $r)
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ $r->producer->brand_name }}</p>
                        <p class="text-xs text-ink-soft">{{ $r->user->name }} <x-lang-badge :lang="$r->language" /> · {{ $r->event?->title_ro }} · {{ $r->created_at->format('d.m.Y') }}</p>
                    </div>
                    @include('site.partials.stars', ['rating' => $r->rating])
                </div>
                @if ($r->body)<p class="mt-3 text-sm">„{{ $r->body }}”</p>@endif
                @if ($r->product_name)<p class="mt-1 text-xs text-ink-soft">{{ __('Produs') }}: {{ $r->product_name }}</p>@endif
                @if ($r->photoUrl())<img src="{{ $r->photoUrl() }}" alt="" class="mt-3 h-32 rounded-xl object-cover">@endif
                <form method="POST" action="{{ route('crm.reviews.moderate', $r) }}" class="mt-4 flex flex-wrap items-center gap-2">@csrf
                    <input name="moderation_note" placeholder="{{ __('Notă internă (opțional)') }}" class="field-input !w-auto flex-1 !py-1.5">
                    @if ($r->status->value !== 'approved')<button name="status" value="approved" class="btn-primary btn-sm">✓ {{ __('Aprobă') }}</button>@endif
                    @if ($r->status->value !== 'rejected')<button name="status" value="rejected" class="btn-ghost btn-sm">✕ {{ __('Respinge') }}</button>@endif
                </form>
                @if ($r->moderator)<p class="mt-2 text-xs text-ink-soft">{{ __('Moderat de') }} {{ $r->moderator->name }}, {{ $r->moderated_at->format('d.m.Y H:i') }}</p>@endif
            </div>
        @empty
            <x-empty class="lg:col-span-2" :title="__('Nicio recenzie în această categorie')" />
        @endforelse
    </div>
    {{ $reviews->links() }}
</x-layouts.crm>
