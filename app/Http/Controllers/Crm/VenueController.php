<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function index(): View
    {
        return view('crm.venues.index', ['venues' => Venue::with(['layouts' => fn ($q) => $q->withCount('slots')->latest('id')])->withCount('events')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('crm.venues.form', ['venue' => new Venue(['active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $venue = Venue::create($data);
        VenueLayout::create(['venue_id' => $venue->id, 'name' => 'Layout '.$venue->name, 'is_master' => true, 'width_m' => 40, 'height_m' => 30]);

        return redirect()->route('crm.venues.index')->with('success', __('Locația a fost creată. Deschide editorul pentru a desena harta.'));
    }

    public function edit(Venue $venue): View
    {
        return view('crm.venues.form', ['venue' => $venue]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $venue->update($this->validated($request, $venue));

        return redirect()->route('crm.venues.index')->with('success', __('Locația a fost actualizată.'));
    }

    protected function validated(Request $request, ?Venue $venue = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('venues', 'code')->ignore($venue?->id)],
            'city' => ['required', 'string', 'max:120'],
            'city_hu' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_hu' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'description_ro' => ['nullable', 'string', 'max:5000'],
            'description_hu' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
