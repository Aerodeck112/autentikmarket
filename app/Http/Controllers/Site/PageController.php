<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('site.pages.about');
    }

    public function forProducers(): View
    {
        return view('site.pages.for-producers');
    }

    /** Autentik Shop este o aplicație EXTERNĂ — aici doar prezentăm linkul configurabil. */
    public function shop(): View
    {
        return view('site.pages.shop', ['url' => Settings::shopUrl()]);
    }

    public function faq(): View
    {
        return view('site.pages.faq');
    }

    public function terms(): View
    {
        return view('site.pages.legal', ['page' => 'terms']);
    }

    public function privacy(): View
    {
        return view('site.pages.legal', ['page' => 'privacy']);
    }

    public function cookies(): View
    {
        return view('site.pages.legal', ['page' => 'cookies']);
    }
}
