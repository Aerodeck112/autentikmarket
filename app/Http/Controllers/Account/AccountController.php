<?php

namespace App\Http\Controllers\Account;

use App\Enums\RewardStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(protected LoyaltyService $loyalty) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadCount(['checkins', 'reviews']);

        return view('account.index', [
            'user' => $user,
            'next' => $this->loyalty->nextRule($user),
            'availableRewards' => $user->rewards()->where('status', RewardStatus::Available->value)->with('rule')->get(),
            'upcoming' => Event::upcoming()->limit(2)->get(),
            'lastCheckins' => $user->checkins()->with('event')->latest('checked_in_at')->limit(3)->get(),
        ]);
    }

    public function card(Request $request): View
    {
        $user = $request->user()->loadCount('checkins');
        $next = $this->loyalty->nextRule($user);

        return view('account.card', [
            'user' => $user,
            'next' => $next,
            'progress' => $next ? min(100, round($user->checkins_count / max(1, $next->threshold) * 100)) : 100,
            'rewards' => $user->rewards()->with(['rule', 'redemption'])->latest('earned_at')->get(),
        ]);
    }

    /** QR-ul cardului: URL cu token aleator — FĂRĂ date personale în clar. */
    public function qr(Request $request): Response
    {
        return response(QrCode::svg(self::cardPayload($request->user())), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function cardPayload(User $user): string
    {
        return route('staff.scan', $user->card_token);
    }

    public function regenerateCard(Request $request): RedirectResponse
    {
        $request->user()->regenerateCardToken();

        return back()->with('success', __('Codul QR a fost regenerat. Vechiul cod nu mai este valabil.'));
    }

    public function history(Request $request): View
    {
        $user = $request->user();

        return view('account.history', [
            'checkins' => $user->checkins()->with('event')->latest('checked_in_at')->paginate(20),
            'interactions' => $user->interactions()->with(['producer', 'event'])->latest('id')->limit(30)->get(),
        ]);
    }

    public function notifications(Request $request): View
    {
        $user = $request->user();
        $ids = [['type' => User::class, 'id' => $user->id]];
        if ($user->producer) {
            $ids[] = ['type' => $user->producer->getMorphClass(), 'id' => $user->producer->id];
        }

        $logs = NotificationLog::query()
            ->where(function ($q) use ($ids) {
                foreach ($ids as $i) {
                    $q->orWhere(fn ($w) => $w->where('notifiable_type', $i['type'])->where('notifiable_id', $i['id']));
                }
            })
            ->latest('id')->paginate(20);

        return view('account.notifications', ['logs' => $logs]);
    }
}
