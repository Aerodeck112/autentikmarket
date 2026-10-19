<x-layouts.crm :title="__('Dashboard')">
    @can('settings.manage')
        @unless (\App\Support\MailSettings::delivers())
            <a href="{{ route('crm.settings.index') }}#email" class="mb-6 flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 hover:bg-red-100">
                <span class="text-lg">✉️</span>
                <span><strong>{{ __('E-mailurile NU se trimit.') }}</strong> {{ __('SMTP nu este configurat. Configurează-l în Setări → E-mail (SMTP).') }}</span>
            </a>
        @endunless
    @endcan
    @php $e = $s['next_event']; @endphp
    @php $ns = $s['next_stats']; @endphp
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- URMĂTORUL TÂRG --}}
        <div class="relative overflow-hidden rounded-[var(--radius-card)] bg-forest-dark p-6 text-cream xl:col-span-2 grain">
            <div class="relative z-10">
                <p class="eyebrow !text-honey">{{ __('Următorul târg') }}</p>
                @if ($e)
                    <div class="mt-2 flex flex-wrap items-end justify-between gap-6">
                        <div>
                            <h2 class="text-3xl">{{ $e->t('title') }}</h2>
                            <p class="mt-1 capitalize text-cream/70">{{ $e->city }} · {{ format_date_local($e->event_date, true) }} · {{ $e->timeRange() }}</p>
                            <x-status :status="$e->status" class="mt-2" />
                        </div>
                        <div x-data="countdown('{{ $e->startsAt()->toIso8601String() }}')" class="flex gap-2 text-center">
                            @foreach (['d' => __('zile'), 'h' => __('ore'), 'm' => __('min')] as $k => $l)
                                <div class="rounded-xl bg-white/10 px-3 py-2"><p class="font-display text-2xl tabular-nums" x-text="pad({{ $k }})"></p><p class="text-[10px] uppercase text-cream/60">{{ $l }}</p></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-cream/60">{{ __('Producători acceptați') }}</p><p class="font-display text-2xl">{{ $ns['producers_accepted'] }}</p></div>
                        <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-cream/60">{{ __('Standuri ocupate') }}</p><p class="font-display text-2xl">{{ $ns['slots_occupied'] }} / {{ $ns['slots_total'] }}</p></div>
                        <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-cream/60">{{ __('Grad ocupare') }}</p><p class="font-display text-2xl">{{ $ns['occupancy_pct'] }}%</p></div>
                        <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-cream/60">{{ __('Cereri în așteptare') }}</p><p class="font-display text-2xl">{{ $ns['slots_pending'] }}</p></div>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-honey" style="width: {{ min(100, $ns['occupancy_pct']) }}%"></div></div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('crm.events.show', $e) }}" class="btn bg-honey text-ink btn-sm">{{ __('Deschide evenimentul') }}</a>
                        @can('reservations.manage')<a href="{{ route('crm.events.map', $e) }}" class="btn border border-cream/30 text-cream btn-sm">{{ __('Hartă & standuri') }}</a>@endcan
                    </div>
                @else
                    <p class="mt-3 text-cream/70">{{ __('Niciun târg programat.') }}</p>
                    @can('events.manage')<a href="{{ route('crm.events.create') }}" class="btn bg-honey text-ink btn-sm mt-4">{{ __('Creează eveniment') }}</a>@endcan
                @endif
            </div>
        </div>

        {{-- TASK-URI --}}
        <div class="card card-pad">
            <div class="flex items-center justify-between"><h2 class="text-xl">{{ __('Task-uri') }}</h2><a href="{{ route('crm.tasks.index') }}" class="text-sm text-clay">{{ __('Toate') }} →</a></div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <a href="{{ route('crm.tasks.index', ['view' => 'overdue']) }}" class="rounded-xl bg-red-50 p-3"><p class="font-display text-2xl text-red-700">{{ $s['tasks_overdue'] }}</p><p class="text-[11px] text-red-800">{{ __('întârziate') }}</p></a>
                <a href="{{ route('crm.tasks.index', ['view' => 'today']) }}" class="rounded-xl bg-amber-50 p-3"><p class="font-display text-2xl text-amber-700">{{ $s['tasks_today'] }}</p><p class="text-[11px] text-amber-800">{{ __('azi') }}</p></a>
                <a href="{{ route('crm.tasks.index', ['view' => 'week']) }}" class="rounded-xl bg-cream p-3"><p class="font-display text-2xl">{{ $s['tasks_week'] }}</p><p class="text-[11px] text-ink-soft">{{ __('7 zile') }}</p></a>
            </div>
            <ul class="mt-4 divide-y divide-line text-sm">
                @foreach ($tasks as $t)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span @class(['truncate', 'text-red-700' => $t->isOverdue()])>{{ $t->t('title') }}</span>
                        <span class="shrink-0 text-xs text-ink-soft">{{ $t->due_date?->format('d.m') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Producători noi')" :value="$s['producers_new']" :href="route('crm.producers.index', ['filter' => 'new'])" />
        <x-stat :label="__('Producători în verificare')" :value="$s['producers_review']" :href="route('crm.producers.index', ['filter' => 'review'])" :tone="$s['producers_review'] ? 'warn' : 'default'" />
        <x-stat :label="__('Producători aprobați')" :value="$s['producers_approved']" :href="route('crm.producers.index', ['filter' => 'approved'])" tone="good" />
        <x-stat :label="__('Cereri stand în așteptare')" :value="$pendingReservations" :tone="$pendingReservations ? 'warn' : 'default'" :href="$e ? route('crm.reservations.index', $e) : null" />
        <x-stat :label="__('Vizitatori total')" :value="$s['visitors_total']" :href="route('crm.visitors.index')" />
        <x-stat :label="__('Vizitatori noi (30 zile)')" :value="$s['visitors_new_30d']" :href="route('crm.visitors.index', ['filter' => 'new'])" />
        <x-stat :label="__('Vizitatori recurenți')" :value="$s['visitors_returning']" :href="route('crm.visitors.index', ['filter' => 'returning'])" />
        <x-stat :label="__('Recenzii de moderat')" :value="$pendingReviews" :tone="$pendingReviews ? 'warn' : 'default'" :href="route('crm.reviews.index')" />
    </div>

    {{-- LIMBI --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([['title' => __('Vizitatori'), 'ro' => $s['visitors_ro'], 'hu' => $s['visitors_hu'], 'rop' => $s['visitors_ro_pct'], 'hup' => $s['visitors_hu_pct']], ['title' => __('Producători'), 'ro' => $s['producers_ro'], 'hu' => $s['producers_hu'], 'rop' => $s['producers_ro_pct'], 'hup' => $s['producers_hu_pct']]] as $l)
            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Limbi') }} — {{ $l['title'] }}</h2>
                <div class="mt-4 flex h-8 overflow-hidden rounded-full text-xs font-bold">
                    <div class="flex items-center justify-center bg-forest text-cream" style="width: {{ max($l['rop'], 0.5) }}%">@if ($l['rop'] >= 10) RO {{ $l['rop'] }}% @endif</div>
                    <div class="flex items-center justify-center bg-sun text-forest-dark" style="width: {{ max($l['hup'], 0.5) }}%">@if ($l['hup'] >= 10) HU {{ $l['hup'] }}% @endif</div>
                </div>
                <div class="mt-3 flex justify-between text-sm">
                    <span><x-lang-badge lang="ro" /> {{ $l['ro'] }} ({{ $l['rop'] }}%)</span>
                    <span><x-lang-badge lang="hu" /> {{ $l['hu'] }} ({{ $l['hup'] }}%)</span>
                </div>
            </div>
        @endforeach
    </div>

    @if ($unreadMessages)
        <a href="{{ route('crm.messages.index') }}" class="mt-6 block rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">✉️ {{ __(':n mesaje de contact nerezolvate', ['n' => $unreadMessages]) }} →</a>
    @endif
</x-layouts.crm>
