<x-layouts.crm :title="__('Task-uri')">
    <x-slot:actions>@can('tasks.manage')<a href="{{ route('crm.task-templates.index') }}" class="btn-ghost">{{ __('Template-uri task') }}</a>@endcan</x-slot:actions>
    @php $views = ['open' => __('Deschise'), 'overdue' => __('Întârziate').' ('.$counts['overdue'].')', 'today' => __('Azi').' ('.$counts['today'].')', 'week' => __('Următoarele 7 zile').' ('.$counts['week'].')', 'mine' => __('Ale mele'), 'done' => __('Finalizate'), 'all' => __('Toate')]; @endphp
    <form class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($views as $k => $l)<a href="{{ route('crm.tasks.index', array_filter(['view' => $k, 'event' => request('event')])) }}" @class(['chip', 'chip-active' => $view === $k])>{{ $l }}</a>@endforeach
        <input type="hidden" name="view" value="{{ $view }}">
        <select name="event" onchange="this.form.submit()" class="field-input !w-auto !py-1.5"><option value="">{{ __('Toate evenimentele') }}</option>@foreach ($events as $e)<option value="{{ $e->id }}" @selected(request('event') == $e->id)>{{ $e->title_ro }} · {{ $e->event_date->format('d.m') }}</option>@endforeach</select>
    </form>
    <div class="grid gap-6 xl:grid-cols-4">
        <div class="card overflow-x-auto xl:col-span-3">
            <table class="table-crm">
                <thead><tr><th></th><th>{{ __('Task') }}</th><th>{{ __('Eveniment') }}</th><th>{{ __('Termen') }}</th><th>{{ __('Prioritate') }}</th><th>{{ __('Responsabil') }}</th></tr></thead>
                <tbody>
                @forelse ($tasks as $t)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('crm.tasks.update', $t) }}">@csrf @method('PUT')
                                <button name="status" value="{{ $t->status->value === 'done' ? 'open' : 'done' }}" class="grid size-5 place-items-center rounded border {{ $t->status->value === 'done' ? 'border-forest bg-forest text-cream' : 'border-line' }}" @cannot('tasks.manage') disabled @endcannot>{{ $t->status->value === 'done' ? '✓' : '' }}</button>
                            </form>
                        </td>
                        <td><p @class(['font-medium', 'line-through text-ink-soft' => $t->status->value === 'done'])>{{ $t->t('title') }}</p>@if ($t->description)<p class="text-xs text-ink-soft">{{ Str::limit($t->description, 80) }}</p>@endif</td>
                        <td class="text-xs">{{ $t->event?->title_ro }}</td>
                        <td @class(['whitespace-nowrap text-sm', 'font-semibold text-red-700' => $t->isOverdue()])>{{ $t->due_date?->format('d.m.Y') ?? '—' }}</td>
                        <td><x-status :status="$t->priority" /></td>
                        <td>
                            @can('tasks.manage')
                                <form method="POST" action="{{ route('crm.tasks.update', $t) }}">@csrf @method('PUT')
                                    <select name="assigned_to" onchange="this.form.submit()" class="field-input !w-auto !py-1 text-xs"><option value="">{{ $t->responsible_role ?: '—' }}</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($t->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select>
                                </form>
                            @else
                                <span class="text-xs">{{ $t->assignee?->name ?? $t->responsible_role }}</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-ink-soft">{{ __('Niciun task.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @can('tasks.manage')
            <form method="POST" action="{{ route('crm.tasks.store') }}" class="card card-pad space-y-3">@csrf
                <h2 class="text-xl">+ {{ __('Task nou') }}</h2>
                <x-input name="title_ro" :label="__('Titlu RO')" required />
                <x-input name="title_hu" :label="__('Titlu HU')" />
                <x-textarea name="description" :label="__('Descriere')" rows="2" />
                <x-select name="event_id" :label="__('Eveniment')" :options="$events->pluck('title_ro', 'id')->all()" placeholder="—" />
                <x-input name="due_date" type="date" :label="__('Termen')" />
                <x-select name="assigned_to" :label="__('Responsabil')" :options="$staff->pluck('name', 'id')->all()" placeholder="—" />
                <x-select name="priority" :label="__('Prioritate')" :options="\App\Enums\TaskPriority::options()" value="normal" />
                <button class="btn-primary">{{ __('Adaugă') }}</button>
            </form>
        @endcan
    </div>
    {{ $tasks->links() }}
</x-layouts.crm>
