<x-layouts.crm :title="$campaign->exists ? __('Editează campania') : __('Campanie nouă')">
    <x-slot:breadcrumb><a href="{{ route('crm.campaigns.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Campanii e-mail') }}</a></x-slot:breadcrumb>
    <form method="POST" action="{{ $campaign->exists ? route('crm.campaigns.update', $campaign) : route('crm.campaigns.store') }}" class="grid gap-6 lg:grid-cols-3"
          x-data="{ audience: @js(old('audience', $campaign->audience)), needsEvent: @js(collect(\App\Services\Campaigns\CampaignService::AUDIENCES)->filter(fn ($a) => $a[1])->keys()->values()) }">
        @csrf @if ($campaign->exists) @method('PUT') @endif
        <div class="card card-pad space-y-4 lg:col-span-2">
            <x-input name="name" :label="__('Nume intern (nu apare în e-mail)')" :value="$campaign->name" required />
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-3">
                    <p class="text-sm font-semibold">🇷🇴 {{ __('Română') }}</p>
                    <x-input name="subject_ro" :label="__('Subiect RO')" :value="$campaign->subject_ro" required />
                    <x-textarea name="body_ro" :label="__('Text RO')" :value="$campaign->body_ro" rows="14" required />
                </div>
                <div class="space-y-3">
                    <p class="text-sm font-semibold">🇭🇺 {{ __('Maghiară') }}</p>
                    <x-input name="subject_hu" :label="__('Subiect HU')" :value="$campaign->subject_hu" required />
                    <x-textarea name="body_hu" :label="__('Text HU')" :value="$campaign->body_hu" rows="14" required />
                </div>
            </div>
            <p class="text-xs text-ink-soft">{{ __('Text simplu: un rând gol începe un paragraf nou; adresele https://… devin linkuri. :ph se înlocuiește cu prenumele destinatarului.', ['ph' => '{'.'{name}'.'}']) }}</p>
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-4">
                <div>
                    <label class="field-label" for="audience">{{ __('Public') }} <span class="text-clay">*</span></label>
                    <select id="audience" name="audience" x-model="audience" class="field-input" required>
                        @foreach (\App\Services\Campaigns\CampaignService::audienceLabels() as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                    @error('audience')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div x-show="needsEvent.includes(audience)" x-cloak>
                    <x-select name="event_id" :label="__('Ediția')" :options="$events" :value="$campaign->event_id" :placeholder="__('Alege ediția…')" />
                </div>
                <p class="text-xs text-ink-soft">{{ __('Numărul exact de destinatari îl vezi după salvare, înainte de trimitere.') }}</p>
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Salvează ciorna') }}</button>
        </div>
    </form>
</x-layouts.crm>
