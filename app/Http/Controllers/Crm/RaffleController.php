<?php

namespace App\Http\Controllers\Crm;

use App\Enums\RaffleStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Raffle;
use App\Models\RaffleDraw;
use App\Services\AuditLogger;
use App\Services\Raffles\RaffleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RaffleController extends Controller
{
    public function __construct(protected RaffleService $service) {}

    public function index(): View
    {
        return view('crm.raffles.index', ['raffles' => Raffle::with(['event', 'currentDraw'])->withCount('entries')->latest('id')->paginate(25)]);
    }

    public function create(Request $request): View
    {
        return view('crm.raffles.form', [
            'raffle' => new Raffle(['winners_count' => 1, 'requires_checkin' => true, 'auto_enroll_on_checkin' => true, 'event_id' => $request->integer('event') ?: null]),
            'events' => Event::orderByDesc('event_date')->limit(30)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $raffle = DB::transaction(function () use ($request) {
            [$data, $prizes] = $this->validated($request);
            $raffle = Raffle::create($data);
            $this->syncPrizes($raffle, $prizes);

            return $raffle;
        });

        return redirect()->route('crm.raffles.show', $raffle)->with('success', __('Tombola a fost creată.'));
    }

    public function show(Raffle $raffle): View
    {
        $raffle->load(['event', 'prizes', 'draws.performer', 'draws.winners.user', 'draws.winners.prize']);

        return view('crm.raffles.show', [
            'raffle' => $raffle,
            'eligibleCount' => count($this->service->eligibleUserIds($raffle)),
            'entries' => $raffle->entries()->with('user')->latest('id')->paginate(50),
        ]);
    }

    public function edit(Raffle $raffle): View
    {
        return view('crm.raffles.form', ['raffle' => $raffle->load('prizes'), 'events' => Event::orderByDesc('event_date')->limit(30)->get()]);
    }

    public function update(Request $request, Raffle $raffle): RedirectResponse
    {
        if ($raffle->status === RaffleStatus::Drawn) {
            return back()->with('error', __('O tombolă extrasă nu mai poate fi modificată.'));
        }
        DB::transaction(function () use ($request, $raffle) {
            [$data, $prizes] = $this->validated($request);
            $raffle->update($data);
            $this->syncPrizes($raffle, $prizes);
        });

        return redirect()->route('crm.raffles.show', $raffle)->with('success', __('Tombola a fost actualizată.'));
    }

    public function status(Request $request, Raffle $raffle): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(['draft', 'open', 'closed', 'cancelled'])]]);
        if ($raffle->status === RaffleStatus::Drawn) {
            return back()->with('error', __('O tombolă extrasă nu își mai poate schimba statusul.'));
        }
        $old = $raffle->status;
        $raffle->forceFill(['status' => $request->input('status')])->save();
        AuditLogger::log('raffle.status_changed', $raffle, ['status' => $old], ['status' => $raffle->status]);

        return back()->with('success', __('Statusul tombolei a fost actualizat.'));
    }

    public function draw(Request $request, Raffle $raffle): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);
        $draw = $this->service->draw($raffle, $request->user());

        return back()->with('success', __('Extragere efectuată: :n câștigători din :p participanți eligibili.', ['n' => $draw->winners()->count(), 'p' => $draw->participants_count]));
    }

    public function void(Request $request, RaffleDraw $draw): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->service->void($draw, $request->user(), $request->input('reason'));

        return back()->with('success', __('Extragerea a fost anulată (înregistrat în audit). Poți efectua o nouă extragere.'));
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'name_ro' => ['required', 'string', 'max:200'],
            'name_hu' => ['required', 'string', 'max:200'],
            'description_ro' => ['nullable', 'string', 'max:3000'],
            'description_hu' => ['nullable', 'string', 'max:3000'],
            'conditions_ro' => ['nullable', 'string', 'max:3000'],
            'conditions_hu' => ['nullable', 'string', 'max:3000'],
            'entry_start' => ['nullable', 'date'],
            'entry_end' => ['nullable', 'date', 'after_or_equal:entry_start'],
            'draw_at' => ['nullable', 'date'],
            'winners_count' => ['required', 'integer', 'min:1', 'max:500'],
            'min_visits' => ['nullable', 'integer', 'min:0', 'max:500'],
            'requires_checkin' => ['nullable', 'boolean'],
            'auto_enroll_on_checkin' => ['nullable', 'boolean'],
            'prizes' => ['nullable', 'array', 'max:50'],
            'prizes.*.name_ro' => ['nullable', 'string', 'max:200'],
            'prizes.*.name_hu' => ['nullable', 'string', 'max:200'],
            'prizes.*.quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $prizes = array_values(array_filter($data['prizes'] ?? [], fn ($p) => filled($p['name_ro'] ?? null)));
        unset($data['prizes']);
        $data['requires_checkin'] = $request->boolean('requires_checkin');
        $data['auto_enroll_on_checkin'] = $request->boolean('auto_enroll_on_checkin');
        $data['min_visits'] = (int) ($data['min_visits'] ?? 0);

        return [$data, $prizes];
    }

    protected function syncPrizes(Raffle $raffle, array $prizes): void
    {
        $raffle->prizes()->delete();
        foreach ($prizes as $i => $p) {
            $raffle->prizes()->create(['name_ro' => $p['name_ro'], 'name_hu' => $p['name_hu'] ?: $p['name_ro'], 'quantity' => (int) ($p['quantity'] ?? 1), 'sort_order' => $i]);
        }
    }
}
