<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Services\Checkin\InteractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Clientul scanează QR-ul de la standul producătorului: „AM CUMPĂRAT DE AICI”.
 */
class InteractionController extends Controller
{
    public function __construct(protected InteractionService $interactions) {}

    public function show(string $token): View
    {
        $producer = Producer::where('qr_token', $token)->approved()->firstOrFail();

        return view('account.interaction', [
            'producer' => $producer,
            'event' => $this->interactions->currentEventFor($producer),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $producer = Producer::where('qr_token', $token)->approved()->firstOrFail();
        $event = $this->interactions->currentEventFor($producer);
        if (! $event) {
            return back()->with('error', __('Producătorul nu participă astăzi la un târg Autentik Market.'));
        }
        $this->interactions->record($event, $producer, $request->user(), 'customer_scan');

        return redirect()->route('account.history')->with('success', __('Mulțumim! După târg vei putea lăsa o recenzie pentru :name.', ['name' => $producer->brand_name]));
    }
}
