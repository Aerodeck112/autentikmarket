<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyRule;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Regulile de fidelitate NU sunt hardcodate: praguri configurabile. */
class LoyaltyController extends Controller
{
    public function index(): View
    {
        return view('crm.loyalty.index', [
            'rules' => LoyaltyRule::withCount(['rewards', 'rewards as redeemed_count' => fn ($q) => $q->where('status', 'redeemed')])->orderBy('threshold')->get(),
            'recent' => RewardRedemption::with(['reward.rule', 'user', 'operator', 'event'])->latest('redeemed_at')->limit(20)->get(),
            'available' => Reward::where('status', 'available')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = LoyaltyRule::create($this->validated($request));
        AuditLogger::log('loyalty_rule.created', $rule, [], $rule->only(['threshold', 'title_ro']));

        return back()->with('success', __('Regula a fost adăugată.'));
    }

    public function update(Request $request, LoyaltyRule $rule): RedirectResponse
    {
        $old = $rule->only(['threshold', 'title_ro', 'active']);
        $rule->update($this->validated($request, $rule));
        AuditLogger::log('loyalty_rule.updated', $rule, $old, $rule->only(['threshold', 'title_ro', 'active']));

        return back()->with('success', __('Regula a fost actualizată.'));
    }

    public function destroy(LoyaltyRule $rule): RedirectResponse
    {
        if ($rule->rewards()->exists()) {
            $rule->update(['active' => false]);

            return back()->with('info', __('Regula are recompense acordate, așa că a fost doar dezactivată.'));
        }
        $rule->delete();

        return back()->with('success', __('Regula a fost ștearsă.'));
    }

    protected function validated(Request $request, ?LoyaltyRule $rule = null): array
    {
        $data = $request->validate([
            'threshold' => ['required', 'integer', 'min:1', 'max:1000', Rule::unique('loyalty_rules', 'threshold')->ignore($rule?->id)],
            'title_ro' => ['required', 'string', 'max:150'],
            'title_hu' => ['required', 'string', 'max:150'],
            'description_ro' => ['nullable', 'string', 'max:1000'],
            'description_hu' => ['nullable', 'string', 'max:1000'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
