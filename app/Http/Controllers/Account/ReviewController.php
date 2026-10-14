<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ProducerCustomerInteraction;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Recenzii: doar pentru interacțiuni reale client → producător → eveniment. Intră în moderare.
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.reviews', [
            'reviews' => $user->reviews()->with(['producer', 'event'])->latest('id')->get(),
            'pending' => $user->interactions()->with(['producer', 'event'])
                ->whereDoesntHave('producer.reviews', fn ($q) => $q->where('user_id', $user->id)->whereColumn('reviews.event_id', 'producer_customer_interactions.event_id'))
                ->latest('id')->limit(20)->get(),
        ]);
    }

    public function create(Request $request, ProducerCustomerInteraction $interaction): View|RedirectResponse
    {
        abort_unless($interaction->user_id === $request->user()->id, 403);
        $existing = Review::where(['user_id' => $interaction->user_id, 'producer_id' => $interaction->producer_id, 'event_id' => $interaction->event_id])->first();
        if ($existing) {
            return redirect()->route('account.reviews')->with('info', __('Ai lăsat deja o recenzie pentru acest producător la acest târg.'));
        }

        return view('account.review-form', ['interaction' => $interaction->load(['producer', 'event'])]);
    }

    public function store(Request $request, ProducerCustomerInteraction $interaction): RedirectResponse
    {
        abort_unless($interaction->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'product_name' => ['nullable', 'string', 'max:150'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('autentik.uploads.image_max_kb')],
        ]);

        $exists = Review::where(['user_id' => $interaction->user_id, 'producer_id' => $interaction->producer_id, 'event_id' => $interaction->event_id])->exists();
        if ($exists) {
            return redirect()->route('account.reviews')->with('info', __('Ai lăsat deja o recenzie pentru acest producător la acest târg.'));
        }

        $photo = $request->hasFile('photo')
            ? $request->file('photo')->storeAs('reviews/'.now()->format('Y/m'), Str::uuid().'.'.$request->file('photo')->extension(), 'public')
            : null;

        Review::create([
            'user_id' => $interaction->user_id,
            'producer_id' => $interaction->producer_id,
            'event_id' => $interaction->event_id,
            'interaction_id' => $interaction->id,
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'photo_path' => $photo,
            'language' => $request->user()->preferredLanguage()->value,
        ]);

        return redirect()->route('account.reviews')->with('success', __('Mulțumim! Recenzia ta a intrat în moderare.'));
    }
}
