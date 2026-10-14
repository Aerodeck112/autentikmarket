<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(): View
    {
        $reviews = Review::approved()->with(['user', 'producer', 'event'])
            ->whereHas('producer', fn ($q) => $q->publicListing())
            ->latest('moderated_at')->paginate(18);

        return view('site.reviews.index', [
            'reviews' => $reviews,
            'average' => round((float) Review::approved()->avg('rating'), 1),
            'total' => Review::approved()->count(),
        ]);
    }
}
