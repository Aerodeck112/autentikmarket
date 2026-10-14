<x-layouts.crm :title="$raffle->name_ro">
    <x-slot:breadcrumb><a href="{{ route('crm.raffles.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Tombole') }}</a></x-slot:breadcrumb>
    <x-slot:actions>@if ($raffle->status->value !== 'drawn')<a href="{{ route('crm.raffles.edit', $raffle) }}" class="btn-ghost">{{ __('Editează') }}</a>@endif</x-slot:actions>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center gap-3"><x-status :status="$raffle->status" /><span class="text-sm text-ink-soft">{{ $raffle->event->title_ro }} · {{ $raffle->event->event_date->format('d.m.Y') }}</span></div>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-cream p-3"><p class="font-display text-3xl">{{ $raffle->entries()->count() }}</p><p class="text-xs text-ink-soft">{{ __('înscriși') }}</p></div>
                    <div class="rounded-xl bg-cream p-3"><p class="font-display text-3xl">{{ $eligibleCount }}</p><p class="text-xs text-ink-soft">{{ __('eligibili acum') }}</p></div>
                    <div class="rounded-xl bg-cream p-3"><p class="font-display text-3xl">{{ $raffle->winners_count }}</p><p class="text-xs text-ink-soft">{{ __('câștigători') }}</p></div>
                </div>
                @if ($raffle->status->value !== 'drawn')
                    <form method="POST" action="{{ route('crm.raffles.status', $raffle) }}" class="mt-4 flex flex-wrap gap-2">@csrf
                        @foreach (['draft' => __('Ciornă'), 'open' => __('Deschide înscrierile'), 'closed' => __('Închide înscrierile'), 'cancelled' => __('Anulează')] as $k => $l)
                            @if ($raffle->status->value !== $k)<button name="status" value="{{ $k }}" class="btn-ghost btn-sm">{{ $l }}</button>@endif
                        @endforeach
                    </form>
                    @can('raffles.draw')
                        <form method="POST" action="{{ route('crm.raffles.draw', $raffle) }}" class="mt-6 rounded-2xl border-2 border-honey bg-honey/10 p-5">@csrf
                            <h3 class="font-display text-xl">{{ __('Extragere') }}</h3>
                            <p class="mt-1 text-sm text-ink-soft">{{ __('La extragere se salvează: snapshot-ul participanților eligibili, numărul lor, hash-ul SHA-256 al listei, câștigătorii, data și administratorul. Randomizare criptografic sigură. Rezultatele nu pot fi modificate, doar anulate cu motiv (audit).') }}</p>
                            <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="confirm" value="1" required class="rounded border-line text-clay"> {{ __('Confirm efectuarea extragerii pentru :n participanți eligibili.', ['n' => $eligibleCount]) }}</label>
                            <button class="btn-accent mt-3" @disabled(! $eligibleCount)>🎲 {{ __('EFECTUEAZĂ EXTRAGEREA') }}</button>
                        </form>
                    @endcan
                @endif
            </div>

            @foreach ($raffle->draws->sortByDesc('id') as $draw)
                <div @class(['card card-pad', 'opacity-60' => $draw->status === 'voided'])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-display text-xl">{{ __('Extragerea') }} #{{ $draw->id }} <x-status :status="$draw->status" /></h3>
                        <span class="text-xs text-ink-soft">{{ $draw->performed_at->format('d.m.Y H:i:s') }} · {{ $draw->performer?->name }}</span>
                    </div>
                    <p class="mt-2 break-all font-mono text-[11px] text-ink-soft">{{ $draw->participants_count }} {{ __('participanți') }} · SHA-256 {{ $draw->snapshot_hash }} · {{ $draw->algorithm }}</p>
                    @if ($draw->void_reason)<p class="mt-2 text-sm text-red-800">{{ __('Anulată') }}: {{ $draw->void_reason }}</p>@endif
                    <table class="table-crm mt-3"><tbody>
                        @foreach ($draw->winners as $w)
                            <tr><td class="w-10 font-display text-lg">{{ $w->position }}</td><td><a href="{{ route('crm.visitors.show', $w->user) }}" class="hover:text-clay">{{ $w->user->name }}</a> <x-lang-badge :lang="$w->user->preferred_language" /></td><td class="text-sm">🎁 {{ $w->prize?->name_ro ?? '—' }}</td><td><x-status :status="$w->status" /></td></tr>
                        @endforeach
                    </tbody></table>
                    @if ($draw->status === 'valid')
                        @can('raffles.draw')
                            <form method="POST" action="{{ route('crm.raffles.void', $draw) }}" class="mt-4 flex gap-2" onsubmit="return confirm('{{ __('Anulezi extragerea? Acțiunea este înregistrată în audit.') }}')">@csrf
                                <input name="reason" required minlength="10" placeholder="{{ __('Motivul anulării (min. 10 caractere)') }}" class="field-input">
                                <button class="btn-danger btn-sm">{{ __('Anulează extragerea') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            @endforeach
        </div>
        <div class="card card-pad">
            <h2 class="text-xl">{{ __('Înscriși') }}</h2>
            @foreach ($entries as $en)
                <p class="mt-2 flex items-center justify-between text-sm"><span>{{ $en->user->name }}</span><span class="text-xs text-ink-soft">{{ $en->source }}</span></p>
            @endforeach
            {{ $entries->links() }}
        </div>
    </div>
</x-layouts.crm>
