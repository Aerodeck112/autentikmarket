<x-layouts.crm :title="$template->name">
    <x-slot:breadcrumb><a href="{{ route('crm.templates.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Template-uri') }}</a></x-slot:breadcrumb>
    <x-slot:actions><a href="{{ route('crm.templates.preview', $template) }}" target="_blank" class="btn-ghost">{{ __('Previzualizare') }}</a></x-slot:actions>
    <form method="POST" action="{{ route('crm.templates.update', $template) }}" class="grid gap-6 lg:grid-cols-4">@csrf @method('PUT')
        <div class="space-y-4 lg:col-span-3">
            <x-input name="name" :label="__('Nume')" :value="$template->name" required />
            <x-textarea name="body_html" :label="__('Conținut HTML')" :value="$template->body_html" rows="28" class="font-mono text-xs" required />
            <x-checkbox name="is_active" :label="__('Activ')" :checked="$template->is_active" />
            <button class="btn-primary">{{ __('Salvează (versiune nouă)') }}</button>
        </div>
        <div class="card card-pad text-sm">
            <h3 class="font-display text-lg">{{ __('Placeholder-e') }}</h3>
            <ul class="mt-2 space-y-1 font-mono text-xs">@foreach ($placeholders as $p)<li>@{{ {{ $p }} }}</li>@endforeach</ul>
        </div>
    </form>
</x-layouts.crm>
