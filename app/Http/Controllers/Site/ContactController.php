<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('site.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot anti-spam: câmpul „website” trebuie să rămână gol.
        if (filled($request->input('website'))) {
            return back()->with('success', __('Mesajul a fost trimis. Îți mulțumim!'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy' => ['accepted'],
        ]);
        unset($data['privacy']);

        ContactMessage::create($data + ['language' => app()->getLocale(), 'ip_address' => $request->ip()]);

        return back()->with('success', __('Mesajul a fost trimis. Îți mulțumim!'));
    }
}
