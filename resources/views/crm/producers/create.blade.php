<x-layouts.crm :title="__('Producător nou')">
    <x-slot:breadcrumb><a href="{{ route('crm.producers.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Producători') }}</a></x-slot:breadcrumb>
    <form method="POST" action="{{ route('crm.producers.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @include('crm.producers._fields')
            <div class="card card-pad space-y-4">
                <h2 class="text-lg">{{ __('Cont și statut') }}</h2>
                @include('crm.producers._invite')
                @can('producers.approve')
                    <x-checkbox name="approve" :label="__('Aprobă direct (fără verificarea documentelor)')" :checked="old('approve', false)"
                                :help="__('Producătorul poate primi imediat stand; documentele le puteți completa ulterior.')" />
                @endcan
            </div>
            <button class="btn-primary btn-lg w-full">{{ __('Adaugă producătorul') }}</button>
        </div>
    </form>
</x-layouts.crm>
