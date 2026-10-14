{{-- CARD AUTENTIK MEMBER — QR fără date personale în clar --}}
<div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-[1.75rem] bg-forest-dark p-6 text-cream shadow-[var(--shadow-lift)] grain">
    <div class="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-honey/25 blur-2xl"></div>
    <div class="relative z-10">
        <div class="flex items-center justify-between">
            <x-logo light />
            <span class="rounded-full border border-cream/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.2em] text-honey">Member</span>
        </div>
        <div class="mt-6 rounded-2xl bg-cream p-3">
            <img src="{{ route('account.qr') }}?v={{ substr(md5($user->card_token), 0, 8) }}" alt="QR" class="mx-auto aspect-square w-full max-w-[220px]">
        </div>
        <div class="mt-5 flex items-end justify-between gap-3">
            <div>
                <p class="font-display text-2xl leading-tight">{{ $user->name }}</p>
                <p class="mt-1 font-mono text-sm tracking-widest text-cream/70">{{ $user->member_code }}</p>
            </div>
            <div class="text-right">
                <p class="font-display text-4xl leading-none text-honey">{{ $user->checkins_count }}</p>
                <p class="text-[10px] uppercase tracking-wider text-cream/60">{{ __('vizite') }}</p>
            </div>
        </div>
    </div>
</div>
