<?php

namespace App\Http\Controllers\Producer;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Producers\ProducerWorkflow;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProducerWorkflow $workflow): View
    {
        $producer = $request->user()->producer->loadCount(['registrations', 'approvedReviews']);

        return view('producer.dashboard', [
            'producer' => $producer,
            'missing' => $workflow->missingFields($producer),
            'openRequests' => $producer->documentRequests()->whereNull('fulfilled_at')->get(),
            'upcoming' => Event::upcoming()->with('venue')->limit(4)->get(),
            'registrations' => $producer->registrations()->with(['event', 'activeReservation.slot'])
                ->whereIn('status', [RegistrationStatus::Submitted->value, RegistrationStatus::Approved->value])
                ->whereHas('event', fn ($q) => $q->whereDate('event_date', '>=', now()->toDateString()))->get(),
            'rating' => round((float) $producer->approvedReviews()->avg('rating'), 1),
        ]);
    }

    public function reviews(Request $request): View
    {
        $producer = $request->user()->producer;

        return view('producer.reviews', [
            'reviews' => $producer->approvedReviews()->with(['user', 'event'])->latest('moderated_at')->paginate(20),
        ]);
    }
}
