<x-layouts.crm :title="__('Template-uri task')">
    <p class="mb-6 max-w-3xl text-sm text-ink-soft">{{ __('La crearea unui târg se generează automat checklist-ul din template-urile „înainte”; la încheierea lui, cele „după”. Termen = data evenimentului + zile relative (negativ = înainte).') }}</p>
    <div class="space-y-2">
        @foreach ($templates as $t)
            <form method="POST" action="{{ route('crm.task-templates.update', $t) }}" class="card grid items-end gap-2 p-3 md:grid-cols-12">@csrf @method('PUT')
                <input name="key" value="{{ $t->key }}" class="field-input font-mono text-xs md:col-span-2">
                <input name="title_ro" value="{{ $t->title_ro }}" class="field-input md:col-span-3">
                <input name="title_hu" value="{{ $t->title_hu }}" class="field-input md:col-span-3">
                <select name="phase" class="field-input md:col-span-1"><option value="pre_event" @selected($t->phase === 'pre_event')>{{ __('înainte') }}</option><option value="post_event" @selected($t->phase === 'post_event')>{{ __('după') }}</option></select>
                <input name="relative_days" type="number" value="{{ $t->relative_days }}" class="field-input md:col-span-1" title="{{ __('zile relative') }}">
                <select name="priority" class="field-input md:col-span-1">@foreach (\App\Enums\TaskPriority::options() as $k => $l)<option value="{{ $k }}" @selected($t->priority->value === $k)>{{ $l }}</option>@endforeach</select>
                <div class="flex items-center gap-2 md:col-span-1">
                    <input type="hidden" name="responsible_role" value="{{ $t->responsible_role }}">
                    <input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($t->active) class="rounded border-line text-forest" title="{{ __('Activ') }}">
                    <button class="btn-ghost btn-sm">✓</button>
                </div>
            </form>
        @endforeach
    </div>
    <form method="POST" action="{{ route('crm.task-templates.store') }}" class="card card-pad mt-6 grid items-end gap-3 md:grid-cols-12">@csrf
        <x-input name="key" label="Key" class="md:col-span-2" placeholder="EVENT_..." required />
        <x-input name="title_ro" :label="__('Titlu RO')" class="md:col-span-3" required />
        <x-input name="title_hu" :label="__('Titlu HU')" class="md:col-span-3" required />
        <x-select name="phase" :label="__('Fază')" :options="['pre_event' => __('înainte'), 'post_event' => __('după')]" class="md:col-span-1" />
        <x-input name="relative_days" type="number" :label="__('Zile')" value="-7" class="md:col-span-1" required />
        <x-select name="priority" :label="__('Prioritate')" :options="\App\Enums\TaskPriority::options()" value="normal" class="md:col-span-1" />
        <div class="md:col-span-1"><input type="hidden" name="responsible_role" value="organizer"><input type="hidden" name="active" value="1"><button class="btn-primary w-full">+</button></div>
    </form>
</x-layouts.crm>
