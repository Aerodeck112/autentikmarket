<x-layouts.crm :title="$visitor->name">
    <x-slot:breadcrumb><a href="{{ route('crm.visitors.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Cumpărători') }}</a></x-slot:breadcrumb>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6">
            <div class="card card-pad">
                <div class="flex items-center justify-between"><p class="font-mono text-sm">{{ $visitor->member_code }}</p><x-lang-badge :lang="$visitor->preferred_language" /></div>
                <p class="mt-3 font-display text-4xl">{{ $visitor->checkins_count }} <span class="text-base text-ink-soft">{{ __('vizite') }}</span></p>
                <dl class="mt-4 space-y-1 text-sm">
                    <div><dt class="inline text-ink-soft">E-mail:</dt> <dd class="inline">{{ $visitor->email ?: '—' }}</dd> @if ($visitor->email_verified_at)✓@endif</div>
                    <div><dt class="inline text-ink-soft">{{ __('Telefon') }}:</dt> <dd class="inline">{{ $visitor->phone ?: '—' }}</dd></div>
                    <div><dt class="inline text-ink-soft">{{ __('Localitate') }}:</dt> <dd class="inline">{{ $visitor->city ?: '—' }}</dd></div>
                    <div><dt class="inline text-ink-soft">{{ __('Înscris') }}:</dt> <dd class="inline">{{ $visitor->created_at->format('d.m.Y') }}</dd></div>
                    <div><dt class="inline text-ink-soft">Newsletter / Marketing:</dt> <dd class="inline">{{ $visitor->newsletter ? '✓' : '—' }} / {{ $visitor->marketing_consent ? '✓' : '—' }}</dd></div>
                </dl>
            </div>
            @can('visitors.manage')
                <form method="POST" action="{{ route('crm.visitors.update', $visitor) }}" class="card card-pad space-y-3">@csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <x-input name="first_name" :label="__('Prenume')" :value="$visitor->first_name" required />
                        <x-input name="last_name" :label="__('Nume')" :value="$visitor->last_name" required />
                    </div>
                    <x-input name="email" type="email" label="E-mail" :value="$visitor->email" />
                    <x-select name="preferred_language" :label="__('Limba de comunicare')" :options="['ro' => 'Română (RO)', 'hu' => 'Magyar (HU)']" :value="$visitor->preferredLanguage()->value" />
                    <x-input name="phone" :label="__('Telefon')" :value="$visitor->phone" />
                    <x-input name="city" :label="__('Localitate')" :value="$visitor->city" />
                    <x-checkbox name="is_active" :label="__('Cont activ')" :checked="$visitor->is_active" />
                    <button class="btn-primary btn-sm">{{ __('Salvează') }}</button>
                </form>
                @if ($visitor->email && ! $visitor->last_login_at)
                    <form method="POST" action="{{ route('crm.visitors.invite', $visitor) }}" class="card card-pad flex flex-wrap items-center justify-between gap-3 text-sm">@csrf
                        <span>{{ __('Contul nu a fost activat încă (nicio autentificare).') }}</span>
                        <button class="btn-ghost btn-sm">✉️ {{ __('Trimite invitația') }}</button>
                    </form>
                @endif
            @endcan
            <div class="card card-pad">
                <h2 class="text-lg">{{ __('Consimțăminte (GDPR)') }}</h2>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($consents as $c)<li>{{ $c->type->label() }} v{{ $c->version }} · {{ $c->granted ? '✓' : '✕' }} · {{ $c->created_at->format('d.m.Y H:i') }} · {{ $c->source === 'crm' ? __('înregistrat în CRM') : $c->ip_address }}</li>@endforeach
                </ul>
            </div>
        </div>
        <div class="space-y-6 xl:col-span-2">
            {{-- EDIȚII ȘI CUMPĂRĂTURI --}}
            @php
                $byEvent = $interactions->groupBy('event_id');
                $visitRows = $checkins->map(fn ($c) => ['event' => $c->event, 'at' => $c->checked_in_at, 'method' => $c->method]);
                foreach ($byEvent as $eventId => $items) {
                    if (! $checkins->contains('event_id', $eventId)) {
                        $visitRows->push(['event' => $items->first()->event, 'at' => null, 'method' => null]);
                    }
                }
                $visitRows = $visitRows->sortByDesc(fn ($r) => $r['event']->event_date)->values();
            @endphp
            <div class="card card-pad">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg">{{ __('Ediții și cumpărături') }}</h2>
                    <p class="text-sm text-ink-soft">{{ trans_choice(':n ediție|:n ediții', $checkins->count(), ['n' => $checkins->count()]) }} · {{ __('a cumpărat de la :n producători', ['n' => $interactions->pluck('producer_id')->unique()->count()]) }}</p>
                </div>
                @if ($visitRows->isEmpty())
                    <p class="mt-3 text-sm text-ink-soft">{{ __('Nicio vizită înregistrată încă.') }}</p>
                @else
                    <table class="table-crm mt-3">
                        <thead><tr><th>{{ __('Ediția') }}</th><th>{{ __('Vizită') }}</th><th>{{ __('A cumpărat de la') }}</th></tr></thead>
                        <tbody>
                        @foreach ($visitRows as $row)
                            <tr>
                                <td><span class="font-semibold">{{ $row['event']->editionLabel() ?? $row['event']->event_date->format('d.m.Y') }}</span><p class="text-xs text-ink-soft">{{ $row['event']->localizedCity() }} · {{ $row['event']->event_date->format('d.m.Y') }}</p></td>
                                <td class="text-sm">@if ($row['at']){{ $row['at']->format('H:i') }} · {{ ['qr' => __('scanare card'), 'manual' => __('check-in manual'), 'crm' => __('înregistrat în CRM')][$row['method']] ?? $row['method'] }}@else<span class="text-ink-soft">—</span>@endif</td>
                                <td class="text-sm">
                                    @forelse ($byEvent->get($row['event']->id, collect()) as $i)
                                        <a href="{{ route('crm.producers.show', $i->producer) }}" class="mb-1 mr-1 inline-block rounded-full bg-sand px-2.5 py-0.5 text-xs hover:bg-wheat">{{ $i->producer->brand_name }}</a>
                                    @empty
                                        <span class="text-ink-soft">—</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
                @can('visitors.manage')
                    <details class="mt-4 rounded-xl border border-line p-4" @if ($errors->has('event_id')) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold">+ {{ __('Înregistrează vizită la târg') }}</summary>
                        <form method="POST" action="{{ route('crm.visitors.visits.store', $visitor) }}" class="mt-3 space-y-3">@csrf
                            <x-crm.visit-fields :events="$events" :producers="$producers" :participants="$participants" :required="true" />
                            <button class="btn-primary btn-sm">{{ __('Salvează vizita') }}</button>
                        </form>
                    </details>
                @endcan
            </div>
            <div class="grid gap-6 md:grid-cols-2">
                <div class="card card-pad"><h2 class="text-lg">{{ __('Recompense') }}</h2>
                    @foreach ($rewards as $r)<p class="mt-2 flex justify-between text-sm"><span>{{ $r->rule->title_ro }}</span><x-status :status="$r->status" /></p>@endforeach
                </div>
                <div class="card card-pad"><h2 class="text-lg">{{ __('Tombole') }}</h2>
                    @foreach ($entries as $en)<p class="mt-2 text-sm">{{ $en->raffle->name_ro }} <span class="text-ink-soft">· {{ $en->source }}</span></p>@endforeach
                    @foreach ($wins as $w)<p class="mt-2 text-sm font-semibold">🏆 {{ $w->raffle->name_ro }} — {{ $w->prize?->name_ro }} <x-status :status="$w->status" /></p>@endforeach
                </div>
                <div class="card card-pad"><h2 class="text-lg">{{ __('Recenzii') }}</h2>
                    @foreach ($reviews as $rv)<p class="mt-2 text-sm">★{{ $rv->rating }} {{ $rv->producer->brand_name }} <x-status :status="$rv->status" /></p>@endforeach
                </div>
            </div>
            <div class="card card-pad">
                <h2 class="text-lg">{{ __('Comunicări primite') }}</h2>
                <table class="table-crm mt-2"><tbody>
                    @forelse ($messages as $m)
                        <tr><td class="text-sm">{{ $m->subject }}</td><td><x-lang-badge :lang="$m->language" /></td><td><x-status :status="$m->status" /></td><td class="text-xs text-ink-soft">{{ $m->created_at->format('d.m.Y H:i') }}</td></tr>
                    @empty
                        <tr><td class="text-sm text-ink-soft">—</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</x-layouts.crm>
