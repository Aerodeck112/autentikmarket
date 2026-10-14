<?php

namespace App\Http\Controllers\Site;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Models\ProducerCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProducerController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->string('category')->toString();
        $q = trim($request->string('q')->toString());

        $producers = Producer::publicListing()->with('category')
            ->withAvg('approvedReviews as rating', 'rating')
            ->when($category, fn ($query) => $query->whereHas('category', fn ($c) => $c->where('slug', $category)))
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('brand_name', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")))
            ->orderBy('brand_name')
            ->paginate(24)->withQueryString();

        return view('site.producers.index', [
            'producers' => $producers,
            'categories' => ProducerCategory::where('active', true)->orderBy('sort_order')->get(),
            'category' => $category,
            'q' => $q,
        ]);
    }

    public function show(Producer $producer): View
    {
        abort_unless($producer->isApproved() && $producer->is_public, 404);

        $producer->load(['category', 'gallery']);
        $reviews = $producer->approvedReviews()->with('user')->latest('moderated_at')->limit(12)->get();
        $events = $producer->registrations()->where('status', RegistrationStatus::Approved->value)
            ->with('event')->get()->pluck('event')->filter()->sortByDesc('event_date')->take(8);

        return view('site.producers.show', [
            'producer' => $producer,
            'reviews' => $reviews,
            'rating' => round((float) $producer->approvedReviews()->avg('rating'), 1),
            'reviewsCount' => $producer->approvedReviews()->count(),
            'events' => $events,
        ]);
    }
}
