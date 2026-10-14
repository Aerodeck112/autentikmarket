<?php

namespace App\Http\Controllers\Account;

use App\Enums\RaffleStatus;
use App\Http\Controllers\Controller;
use App\Models\Raffle;
use App\Services\Raffles\RaffleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RaffleController extends Controller
{
    public function __construct(protected RaffleService $raffles) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $list = Raffle::with(['event', 'prizes', 'currentDraw'])
            ->whereIn('status', [RaffleStatus::Open->value, RaffleStatus::Closed->value, RaffleStatus::Drawn->value])
            ->latest('id')->limit(30)->get()
            ->map(fn (Raffle $r) => ['raffle' => $r, 'status' => $this->raffles->userStatus($r, $user), 'win' => $r->currentDraw?->winners()->where('user_id', $user->id)->with('prize')->first()]);

        return view('account.raffles', ['items' => $list]);
    }

    public function enter(Request $request, Raffle $raffle): RedirectResponse
    {
        if (! $this->raffles->isEligible($raffle, $request->user())) {
            return back()->with('error', __('Nu îndeplinești încă condițiile acestei tombole.'));
        }
        $this->raffles->enter($raffle, $request->user(), 'self');

        return back()->with('success', __('Te-ai înscris la tombolă. Mult noroc!'));
    }
}
