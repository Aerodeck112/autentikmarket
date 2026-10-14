<x-layouts.app :title="__('Devino producător')" area="producer">
    <form method="POST" action="{{ route('producer.onboarding.store') }}" class="card card-pad mx-auto max-w-lg space-y-4">
        @csrf
        <h1 class="text-3xl">{{ __('Creează profilul de producător') }}</h1>
        <x-input name="brand_name" :label="__('Denumirea brandului / fermei / atelierului')" required />
        <x-select name="preferred_language" :label="__('În ce limbă dorești să comunicăm?')" :options="['ro' => 'Română', 'hu' => 'Magyar']" :value="app()->getLocale()" required />
        <button class="btn-primary">{{ __('Continuă') }}</button>
    </form>
</x-layouts.app>
