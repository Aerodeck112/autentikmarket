<x-layouts.crm :title="__('Fidelitate')">
    <p class="mb-6 max-w-3xl text-sm text-ink-soft">{{ __('Regulile nu sunt hardcodate: la atingerea pragului, recompensa se creează automat la check-in, iar clientul primește notificarea în limba lui.') }} {{ __('Recompense disponibile nerevendicate') }}: <strong>{{ $available }}</strong></p>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @foreach ($rules as $rule)
                <form method="POST" action="{{ route('crm.loyalty.update', $rule) }}" class="card card-pad grid gap-3 sm:grid-cols-6">@csrf @method('PUT')
                    <x-input name="threshold" type="number" :label="__('Prag (vizite)')" :value="$rule->threshold" class="sm:col-span-1" required />
                    <x-input name="title_ro" :label="__('Titlu RO')" :value="$rule->title_ro" class="sm:col-span-2" required />
                    <x-input name="title_hu" :label="__('Titlu HU')" :value="$rule->title_hu" class="sm:col-span-2" required />
                    <x-input name="valid_days" type="number" :label="__('Valabil (zile)')" :value="$rule->valid_days" class="sm:col-span-1" />
                    <x-textarea name="description_ro" :label="__('Descriere RO')" :value="$rule->description_ro" rows="2" class="sm:col-span-3" />
                    <x-textarea name="description_hu" :label="__('Descriere HU')" :value="$rule->description_hu" rows="2" class="sm:col-span-3" />
                    <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                        <x-checkbox name="active" :label="__('Activă')" :checked="$rule->active" />
                        <span class="text-xs text-ink-soft">{{ $rule->rewards_count }} {{ __('acordate') }} · {{ $rule->redeemed_count }} {{ __('ridicate') }}</span>
                        <button class="btn-primary btn-sm ml-auto">{{ __('Salvează') }}</button>
                    </div>
                </form>
            @endforeach
            <form method="POST" action="{{ route('crm.loyalty.store') }}" class="card card-pad grid gap-3 border-dashed sm:grid-cols-6">@csrf
                <p class="font-display text-lg sm:col-span-6">+ {{ __('Regulă nouă') }}</p>
                <x-input name="threshold" type="number" :label="__('Prag (vizite)')" class="sm:col-span-1" required />
                <x-input name="title_ro" :label="__('Titlu RO')" class="sm:col-span-2" required />
                <x-input name="title_hu" :label="__('Titlu HU')" class="sm:col-span-2" required />
                <x-input name="valid_days" type="number" :label="__('Valabil (zile)')" class="sm:col-span-1" />
                <x-textarea name="description_ro" :label="__('Descriere RO')" rows="2" class="sm:col-span-3" />
                <x-textarea name="description_hu" :label="__('Descriere HU')" rows="2" class="sm:col-span-3" />
                <div class="flex items-center gap-3 sm:col-span-6"><x-checkbox name="active" :label="__('Activă')" :checked="true" /><button class="btn-primary btn-sm ml-auto">{{ __('Adaugă') }}</button></div>
            </form>
        </div>
        <div class="card card-pad">
            <h2 class="text-xl">{{ __('Ultimele cadouri ridicate') }}</h2>
            @forelse ($recent as $r)
                <div class="mt-3 border-t border-line pt-3 text-sm">
                    <p class="font-medium">{{ $r->reward->rule->title_ro }}</p>
                    <p class="text-xs text-ink-soft">{{ $r->user->name }} · {{ $r->redeemed_at->format('d.m.Y H:i') }} · {{ __('operator') }}: {{ $r->operator?->name }}</p>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-soft">—</p>
            @endforelse
        </div>
    </div>
</x-layouts.crm>
