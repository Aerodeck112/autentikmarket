<?php

namespace App\Http\Controllers\Producer;

use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\Producer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Un utilizator existent (ex. vizitator) își creează profil de producător. */
class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->producer) {
            return redirect()->route('producer.dashboard');
        }

        return view('producer.onboarding');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->producer) {
            return redirect()->route('producer.dashboard');
        }
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:150'],
            'preferred_language' => ['required', Rule::enum(Language::class)],
        ]);
        (new Producer([
            'user_id' => $user->id,
            'brand_name' => $data['brand_name'],
            'preferred_language' => $data['preferred_language'],
            'representative_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'city' => $user->city,
        ]))->save();
        $user->assignRole('producer');

        return redirect()->route('producer.profile')->with('success', __('Profilul de producător a fost creat.'));
    }
}
