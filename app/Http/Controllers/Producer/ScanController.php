<?php

namespace App\Http\Controllers\Producer;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\Checkin\CheckinService;
use App\Services\Checkin\InteractionService;
use App\Support\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Producătorul scanează cardul clientului → relația CLIENT → PRODUCĂTOR → EVENIMENT.
 * Plus QR-ul propriu „Am cumpărat de aici” pentru afișare la stand.
 */
class ScanController extends Controller
{
    public function show(Request $request, InteractionService $interactions): View
    {
        $producer = $request->user()->producer;

        return view('producer.scan', [
            'producer' => $producer,
            'event' => $interactions->currentEventFor($producer),
            'todayCount' => ($e = $interactions->currentEventFor($producer)) ? $producer->interactions()->where('event_id', $e->id)->count() : 0,
        ]);
    }

    public function store(Request $request, CheckinService $checkins, InteractionService $interactions): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:500']]);
        $producer = $request->user()->producer;
        $event = $interactions->currentEventFor($producer);
        if (! $event) {
            return response()->json(['message' => __('Nu participi astăzi la un târg Autentik Market.')], 422);
        }
        $user = $checkins->findUserByToken($request->input('code'));
        if (! $user) {
            return response()->json(['message' => __('Card necunoscut.')], 404);
        }
        try {
            $i = $interactions->record($event, $producer, $user, 'producer_scan');
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'new' => $i->wasRecentlyCreated,
            'name' => $user->first_name.' '.mb_substr((string) $user->last_name, 0, 1).'.',
            'language' => $user->preferredLanguage()->value,
            'message' => $i->wasRecentlyCreated ? __('Cumpărătură înregistrată. Mulțumim!') : __('Clientul era deja înregistrat la standul tău azi.'),
        ]);
    }

    public function qr(Request $request): Response
    {
        $producer = $request->user()->producer;

        return response(QrCode::svg(route('qr.producer', $producer->qr_token)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="autentik-qr-'.$producer->slug.'.svg"',
        ]);
    }
}
