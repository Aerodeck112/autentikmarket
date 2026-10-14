<x-layouts.staff :title="$visitor->name">
    <a href="{{ route('staff.index') }}" class="mt-2 inline-block text-sm text-cream/60">← {{ __('Scanner') }}</a>
    @if (session('success'))<p class="mt-3 rounded-2xl bg-emerald-500 p-3 text-sm font-semibold text-white">{{ session('success') }}</p>@endif
    @if (session('error'))<p class="mt-3 rounded-2xl bg-red-500 p-3 text-sm font-semibold text-white">{{ session('error') }}</p>@endif
    @if ($autoResult)
        <p class="mt-3 rounded-2xl p-3 text-sm font-semibold {{ $autoResult->created ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-ink' }}">{{ $autoResult->created ? __('Check-in reușit!') : __('Vizitatorul are deja check-in la acest eveniment.') }}</p>
    @endif

    <div class="mt-4 rounded-3xl bg-cream p-5 text-ink">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="font-display text-3xl leading-tight">{{ $visitor->name }}</p>
                <p class="font-mono text-sm text-ink-soft">{{ $visitor->member_code }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ $visitor->city }}</p>
            </div>
            <x-lang-badge :lang="$visitor->preferred_language" class="!text-sm" />
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 text-center">
            <div class="rounded-2xl bg-white p-3"><p class="font-display text-3xl">{{ $visitor->checkins_count }}</p><p class="text-xs uppercase text-ink-soft">{{ __('vizite') }}</p></div>
            <div class="rounded-2xl p-3 {{ $checkedIn ? 'bg-emerald-100 text-emerald-900' : 'bg-white' }}"><p class="font-display text-xl">{{ $checkedIn ? '✓' : '—' }}</p><p class="text-xs uppercase">{{ __('check-in azi') }}</p></div>
        </div>
        @if ($event && ! $checkedIn)
            <div x-data="{ busy: false }">
                <button type="button" :disabled="busy" class="btn-primary btn-lg mt-4 w-full" @click="busy = true; window.amFetch('{{ route('staff.checkin') }}', { method: 'POST', body: JSON.stringify({ event_id: {{ $event->id }}, user_id: {{ $visitor->id }} }) }).then(() => location.reload())">{{ __('Check-in manual') }}</button>
            </div>
        @endif
    </div>

    <h2 class="mt-6 font-display text-xl">{{ __('Recompense') }}</h2>
    @forelse ($rewards as $reward)
        <div class="mt-2 flex items-center justify-between gap-3 rounded-2xl bg-white/5 p-4">
            <div>
                <p class="font-semibold">{{ $reward->rule->t('title') }}</p>
                <p class="text-xs text-cream/60">{{ $reward->status->label() }}@if ($reward->redemption) · {{ $reward->redemption->redeemed_at->format('d.m.Y H:i') }}@endif</p>
            </div>
            @if ($reward->status->value === 'available')
                @can('rewards.redeem')
                    <form method="POST" action="{{ route('staff.rewards.redeem', $reward) }}" onsubmit="return confirm('{{ __('Confirmi predarea cadoului?') }}')">@csrf<button class="rounded-full bg-honey px-4 py-2 text-xs font-bold text-ink">{{ __('MARCAȚI CADOU RIDICAT') }}</button></form>
                @endcan
            @endif
        </div>
    @empty
        <p class="mt-2 text-sm text-cream/60">{{ __('Nicio recompensă încă.') }}</p>
    @endforelse

    <h2 class="mt-6 font-display text-xl">{{ __('Tombole câștigate') }}</h2>
    @forelse ($wins as $win)
        <div class="mt-2 flex items-center justify-between gap-3 rounded-2xl bg-white/5 p-4">
            <div>
                <p class="font-semibold">{{ $win->raffle->t('name') }}</p>
                <p class="text-xs text-cream/60">🎁 {{ $win->prize?->t('name') ?? '—' }} · {{ $win->status->label() }}</p>
            </div>
            @if ($win->status->value === 'won')
                @can('raffles.validate')
                    <form method="POST" action="{{ route('staff.winners.claim', $win) }}" onsubmit="return confirm('{{ __('Confirmi predarea premiului?') }}')">@csrf<button class="rounded-full bg-honey px-4 py-2 text-xs font-bold text-ink">{{ __('PREMIU RIDICAT') }}</button></form>
                @endcan
            @endif
        </div>
    @empty
        <p class="mt-2 text-sm text-cream/60">—</p>
    @endforelse
</x-layouts.staff>
