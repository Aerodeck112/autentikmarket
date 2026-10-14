<x-layouts.crm :title="$template->key">
    <x-slot:breadcrumb><a href="{{ route('crm.automations.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Automatizări') }}</a></x-slot:breadcrumb>
    <form method="POST" action="{{ route('crm.automations.update', $template) }}" class="grid gap-6 lg:grid-cols-4">@csrf @method('PUT')
        <div class="grid gap-6 md:grid-cols-2 lg:col-span-3">
            @foreach (['ro' => 'Română', 'hu' => 'Magyar'] as $l => $label)
                <div class="card card-pad space-y-3">
                    <h2 class="flex items-center gap-2 text-xl"><x-lang-badge :lang="$l" /> {{ $label }}</h2>
                    <x-input :name="'subject_'.$l" :label="__('Subiect')" :value="$template->{'subject_'.$l}" required />
                    <x-textarea :name="'body_'.$l" :label="__('Mesaj')" :value="$template->{'body_'.$l}" rows="14" required />
                </div>
            @endforeach
            <div class="md:col-span-2 flex items-center gap-4"><x-checkbox name="active" :label="__('Activ')" :checked="$template->active" /><button class="btn-primary">{{ __('Salvează') }}</button></div>
        </div>
        <div class="card card-pad text-sm">
            <h3 class="font-display text-lg">{{ __('Placeholder-e') }}</h3>
            <ul class="mt-2 space-y-1 font-mono text-xs">@foreach ($placeholders as $p)<li>@{{ {{ $p }} }}</li>@endforeach</ul>
            <p class="mt-4 text-xs text-ink-soft">{{ __('Textul este simplu (fără HTML). Rândurile goale separă paragrafele; linkurile devin automat clicabile. Valorile sunt escapate.') }}</p>
        </div>
    </form>
</x-layouts.crm>
