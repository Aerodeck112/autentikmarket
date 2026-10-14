<x-layouts.crm :title="$event->title_ro">
    <x-slot:breadcrumb><a href="{{ route('crm.events.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Evenimente') }}</a></x-slot:breadcrumb>
    <x-slot:actions>
        @can('reservations.manage')
            <a href="{{ route('crm.events.map', $event) }}" class="btn-ghost">{{ __('Hartă & standuri') }}</a>
            <a href="{{ route('crm.reservations.index', $event) }}" class="btn-ghost">{{ __('Rezervări') }}</a>
        @endcan
        <a href="{{ lroute('events.show', $event->slug) }}" target="_blank" class="btn-ghost">↗ {{ __('Pagina publică') }}</a>
        @can('events.manage')<a href="{{ route('crm.events.edit', $event) }}" class="btn-primary">{{ __('Editează') }}</a>@endcan
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-ink-soft">{{ $event->title_hu }}</p>
                        <p class="mt-1 capitalize">{{ format_date_local($event->event_date, true) }} · {{ $event->timeRange() }} · {{ $event->city }}, {{ $event->venue_name }}</p>
                    </div>
                    <x-status :status="$event->status" />
                </div>
                @can('events.manage')
                    @if ($transitions)
                        <form method="POST" action="{{ route('crm.events.status', $event) }}" class="mt-4 flex flex-wrap items-center gap-2" onsubmit="return confirm('{{ __('Schimbi statusul evenimentului?') }}')">
                            @csrf
                            <span class="text-sm text-ink-soft">{{ __('Schimbă statusul') }}:</span>
                            @foreach ($transitions as $to)
                                <button name="status" value="{{ $to }}" class="btn-ghost btn-sm {{ $to === 'completed' ? '!border-forest !text-forest' : '' }}">{{ \App\Enums\EventStatus::from($to)->label() }}</button>
                            @endforeach
                        </form>
                        <p class="mt-2 text-xs text-ink-soft">{{ __('La „Încheiat” pornește automat workflow-ul post-eveniment: mulțumiri RO/HU, cereri de recenzie, task-uri, draft blog.') }}</p>
                    @endif
                @endcan
            </div>

            {{-- STATISTICI --}}
            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl">{{ __('Statistici') }}</h2>
                    @can('exports.run')
                        <div class="flex flex-wrap gap-1 text-xs">
                            <a class="btn-ghost btn-sm" href="{{ route('crm.events.export', [$event, 'visitors']) }}">{{ __('Participanți') }} CSV</a>
                            <a class="btn-ghost btn-sm" href="{{ route('crm.events.export', [$event, 'visitors', 'format' => 'xlsx']) }}">XLSX</a>
                            <a class="btn-ghost btn-sm" href="{{ route('crm.events.export', [$event, 'producers']) }}">{{ __('Producători') }} CSV</a>
                            <a class="btn-ghost btn-sm" href="{{ route('crm.events.export', [$event, 'producers', 'format' => 'xlsx']) }}">XLSX</a>
                        </div>
                    @endcan
                </div>
                @php $items = [
                    __('Producători înscriși') => $stats['producers_registered'], __('Producători acceptați') => $stats['producers_accepted'],
                    __('Prezenți') => $stats['producers_present'], __('Absenți') => $stats['producers_absent'],
                    __('Standuri disponibile') => $stats['slots_available'], __('Standuri ocupate') => $stats['slots_occupied'].' / '.$stats['slots_total'],
                    __('Grad ocupare') => $stats['occupancy_pct'].'%', __('Vizitatori') => $stats['visitors_total'],
                    __('Vizitatori noi') => $stats['visitors_new'], __('Vizitatori recurenți') => $stats['visitors_returning'],
                    __('Interacțiuni cumpărare') => $stats['interactions'], __('Recenzii') => $stats['reviews'].($stats['rating'] ? ' · ★'.$stats['rating'] : ''),
                    __('Participanți tombolă') => $stats['raffle_participants'], __('Recompense ridicate') => $stats['rewards_redeemed'],
                ]; @endphp
                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($items as $k => $v)
                        <div class="rounded-xl bg-cream/60 p-3"><dt class="text-xs text-ink-soft">{{ $k }}</dt><dd class="font-display text-xl">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-line p-3 text-sm"><p class="text-xs text-ink-soft">{{ __('Vizitatori pe limbi') }}</p><p class="mt-1"><x-lang-badge lang="ro" /> {{ $stats['visitors_ro'] }} ({{ $stats['visitors_ro_pct'] }}%) · <x-lang-badge lang="hu" /> {{ $stats['visitors_hu'] }} ({{ $stats['visitors_hu_pct'] }}%)</p></div>
                    <div class="rounded-xl border border-line p-3 text-sm"><p class="text-xs text-ink-soft">{{ __('Producători pe limbi') }}</p><p class="mt-1"><x-lang-badge lang="ro" /> {{ $stats['producers_ro'] }} ({{ $stats['producers_ro_pct'] }}%) · <x-lang-badge lang="hu" /> {{ $stats['producers_hu'] }} ({{ $stats['producers_hu_pct'] }}%)</p></div>
                </div>
            </div>

            {{-- TASK-URI --}}
            <div class="card card-pad">
                <div class="flex items-center justify-between"><h2 class="text-xl">{{ __('Checklist') }}</h2><a href="{{ route('crm.tasks.index', ['event' => $event->id, 'view' => 'all']) }}" class="text-sm text-clay">{{ __('Toate task-urile') }} →</a></div>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @forelse ($tasks as $task)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <form method="POST" action="{{ route('crm.tasks.update', $task) }}" class="flex items-center gap-3">
                                @csrf @method('PUT')
                                <button name="status" value="{{ $task->status->value === 'done' ? 'open' : 'done' }}" class="grid size-5 place-items-center rounded border {{ $task->status->value === 'done' ? 'border-forest bg-forest text-cream' : 'border-line' }}" @cannot('tasks.manage') disabled @endcannot>{{ $task->status->value === 'done' ? '✓' : '' }}</button>
                                <span @class(['line-through text-ink-soft' => $task->status->value === 'done', 'text-red-700' => $task->isOverdue()])>{{ $task->title_ro }}</span>
                            </form>
                            <span class="flex shrink-0 items-center gap-2 text-xs text-ink-soft">{{ $task->phase === 'post_event' ? __('după') : '' }} {{ $task->due_date?->format('d.m.Y') }} <x-status :status="$task->priority" /></span>
                        </li>
                    @empty
                        <li class="py-2 text-ink-soft">—</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Hartă') }}</h2>
                @if ($event->layout)
                    <p class="mt-2 text-sm text-ink-soft">{{ __('Snapshot din layout-ul master v:v', ['v' => $event->layout->source_version]) }} · {{ $stats['slots_total'] }} {{ __('standuri') }}</p>
                @else
                    <p class="mt-2 text-sm text-ink-soft">{{ __('Evenimentul nu are hartă.') }}</p>
                @endif
                @can('maps.manage')
                    @if ($event->venue)
                        <form method="POST" action="{{ route('crm.events.snapshot', $event) }}" class="mt-3" onsubmit="return confirm('{{ __('Resincronizezi harta cu layout-ul master? Posibil doar fără rezervări.') }}')">@csrf<button class="btn-ghost btn-sm">{{ __('Resincronizează cu master') }}</button></form>
                    @endif
                @endcan
            </div>

            <div class="card card-pad">
                <div class="flex items-center justify-between"><h2 class="text-xl">{{ __('Tombole') }}</h2>@can('raffles.manage')<a href="{{ route('crm.raffles.create', ['event' => $event->id]) }}" class="text-sm text-clay">+ {{ __('Tombolă') }}</a>@endcan</div>
                @foreach ($event->raffles as $r)
                    <a href="{{ route('crm.raffles.show', $r) }}" class="mt-2 flex items-center justify-between text-sm hover:text-clay"><span>{{ $r->name_ro }}</span><x-status :status="$r->status" /></a>
                @endforeach
            </div>

            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Fotografii') }} ({{ $event->photos->count() }})</h2>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($event->photos as $m)
                        <form method="POST" action="{{ route('crm.events.photos.destroy', [$event, $m]) }}" class="group relative">@csrf @method('DELETE')
                            <img src="{{ $m->url() }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                            <button class="absolute right-1 top-1 hidden rounded-full bg-white px-1.5 text-xs text-red-700 group-hover:block">✕</button>
                        </form>
                    @endforeach
                </div>
                @can('events.manage')
                    <form method="POST" action="{{ route('crm.events.photos', $event) }}" enctype="multipart/form-data" class="mt-3 space-y-2">@csrf
                        <input type="file" name="photos[]" multiple accept="image/*" required class="block w-full text-sm">
                        <button class="btn-ghost btn-sm">{{ __('Încarcă fotografii') }}</button>
                    </form>
                @endcan
            </div>

            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Blog') }}</h2>
                @forelse ($event->blogPosts as $p)
                    <a href="{{ route('crm.blog.edit', $p) }}" class="mt-2 flex items-center justify-between text-sm hover:text-clay"><span>{{ Str::limit($p->title_ro, 40) }}</span><x-status :status="$p->status" /></a>
                @empty
                    @can('blog.manage')<form method="POST" action="{{ route('crm.blog.from-event', $event) }}" class="mt-2">@csrf<button class="btn-ghost btn-sm">{{ __('Generează draft din eveniment') }}</button></form>@endcan
                @endforelse
                @if ($event->status->value === 'completed')
                    @can('events.manage')<form method="POST" action="{{ route('crm.events.post-event', $event) }}" class="mt-3">@csrf<button class="btn-ghost btn-sm">{{ __('Rulează din nou workflow-ul post-eveniment') }}</button></form>@endcan
                @endif
            </div>
        </div>
    </div>
</x-layouts.crm>
