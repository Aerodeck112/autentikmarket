<?php

namespace App\Http\Controllers\Crm;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'pending');

        return view('crm.reviews.index', [
            'reviews' => Review::with(['user', 'producer', 'event', 'moderator'])->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => Review::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function moderate(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,pending'], 'moderation_note' => ['nullable', 'string', 'max:1000']]);
        $old = $review->status;
        $review->forceFill([
            'status' => ReviewStatus::from($data['status']),
            'moderation_note' => $data['moderation_note'] ?? null,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();
        AuditLogger::log('review.moderated', $review, ['status' => $old], ['status' => $review->status]);
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');

        return back()->with('success', __('Recenzia a fost moderată.'));
    }
}
