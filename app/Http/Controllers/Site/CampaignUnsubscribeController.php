<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaignRecipient;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Dezabonare din campanii: pagina cu buton (GET nu dezabonează — scanerele de linkuri din e-mail
 * deschid linkurile automat) + POST, folosit și de „dezabonare dintr-un clic” din Gmail/Outlook.
 */
class CampaignUnsubscribeController extends Controller
{
    public function show(EmailCampaignRecipient $recipient): View
    {
        App::setLocale($recipient->language);

        return view('site.unsubscribe', ['recipient' => $recipient, 'done' => (bool) $recipient->unsubscribed_at]);
    }

    public function store(Request $request, EmailCampaignRecipient $recipient, CampaignService $campaigns): View|Response
    {
        App::setLocale($recipient->language);
        if (! $recipient->unsubscribed_at) {
            $campaigns->unsubscribe($recipient, $request->ip());
        }

        return view('site.unsubscribe', ['recipient' => $recipient, 'done' => true]);
    }
}
