<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Review;
use App\Models\StandReservation;
use App\Models\Task;
use App\Services\Stats\StatisticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(StatisticsService $stats): View
    {
        return view('crm.dashboard', [
            's' => $stats->dashboard(),
            'tasks' => Task::open()->with('event')->whereDate('due_date', '<=', now()->addDays(7)->toDateString())->orderBy('due_date')->limit(12)->get(),
            'pendingReservations' => StandReservation::where('status', 'requested')->count(),
            'pendingReviews' => Review::where('status', 'pending')->count(),
            'unreadMessages' => ContactMessage::whereNull('handled_at')->count(),
        ]);
    }
}
