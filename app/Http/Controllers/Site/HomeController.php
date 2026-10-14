<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Media;
use App\Models\Producer;
use App\Models\ProducerCategory;
use App\Models\Review;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Fără cache de modele Eloquent (Laravel 13 restricționează unserialize pe clase) — interogări indexate, ieftine.
        $data = (function () {
            $upcoming = Event::upcoming()->with('venue')->limit(4)->get();

            return [
                'nextEvent' => $upcoming->first(),
                'upcoming' => $upcoming,
                'producers' => Producer::publicListing()->with('category')
                    ->orderByRaw('case when logo_path is null and cover_path is null then 1 else 0 end')
                    ->latest('approved_at')->limit(8)->get(),
                'producersCount' => Producer::publicListing()->count(),
                'categories' => ProducerCategory::where('active', true)->withCount(['producers' => fn ($q) => $q->publicListing()])->orderBy('sort_order')->get(),
                'gallery' => Media::where('collection', 'event_photos')->latest('id')->limit(8)->get(),
                'reviews' => Review::approved()->with(['user', 'producer'])->whereNotNull('body')->latest('moderated_at')->limit(3)->get(),
            ];
        })();

        return view('site.home', $data);
    }
}
