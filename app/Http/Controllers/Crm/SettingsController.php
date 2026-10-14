<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Support\Settings;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('crm.settings.index', [
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Setări → Integrări → Autentik Shop URL (NU este hardcodat).
            'integrations_shop_url' => ['nullable', 'url', 'max:255'],
            'org_name' => ['nullable', 'string', 'max:200'],
            'org_legal_name' => ['nullable', 'string', 'max:200'],
            'org_cui' => ['nullable', 'string', 'max:40'],
            'org_trade_register' => ['nullable', 'string', 'max:60'],
            'org_address' => ['nullable', 'string', 'max:255'],
            'org_representative' => ['nullable', 'string', 'max:150'],
            'org_email' => ['nullable', 'email', 'max:255'],
            'org_phone' => ['nullable', 'string', 'max:40'],
            'org_iban' => ['nullable', 'string', 'max:40'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'reservations_hold_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'mail_reply_to' => ['nullable', 'email', 'max:255'],
            'contracts_number_prefix' => ['required', 'string', 'max:10', 'alpha_num'],
            'seo_default_description_ro' => ['nullable', 'string', 'max:255'],
            'seo_default_description_hu' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'hero' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.config('autentik.uploads.image_max_kb')],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_hero' => ['nullable', 'boolean'],
        ]);

        $map = [];
        foreach ($data as $k => $v) {
            if (in_array($k, ['logo', 'hero', 'remove_logo', 'remove_hero'], true)) {
                continue;
            }
            [$group, $rest] = explode('_', $k, 2);
            $key = $group.'.'.$rest;
            if (str_starts_with($k, 'seo_default_description')) {
                $key = 'seo.'.substr($k, 4);
            }
            $map[$key] = $v ?? '';
        }
        Settings::setMany($map);

        foreach (['logo' => 'brand.logo_path', 'hero' => 'brand.hero_path'] as $field => $key) {
            if ($request->boolean('remove_'.$field) || $request->hasFile($field)) {
                if ($old = Settings::get($key)) {
                    Storage::disk('public')->delete($old);
                }
                Settings::set($key, $request->hasFile($field) ? Uploads::storeImage($request->file($field), 'brand') : '', 'brand');
            }
        }
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');
        AuditLogger::log('settings.updated', null, [], ['keys' => array_keys($map)]);

        return back()->with('success', __('Setările au fost salvate.'));
    }

    /** Admin → Setări Email → „Trimite email test” (prin motorul de notificări + log). */
    public function testEmail(Request $request, NotificationService $notifications): RedirectResponse
    {
        $log = $notifications->send('TEST_EMAIL', $request->user(), [], ['dedupe_key' => 'test-email:'.$request->user()->id.':'.now()->format('YmdHis')]);

        return back()->with($log ? 'success' : 'error', $log
            ? __('E-mailul de test a fost pus în coadă către :e (status: :s). Verifică jurnalul de notificări.', ['e' => $request->user()->email, 's' => $log->fresh()->status->label()])
            : __('Template-ul TEST_EMAIL lipsește sau este inactiv.'));
    }

    public function messages(): View
    {
        return view('crm.settings.messages', ['messages' => ContactMessage::latest('id')->paginate(30)]);
    }

    public function handled(Request $request, ContactMessage $message): RedirectResponse
    {
        $message->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);

        return back()->with('success', __('Mesaj marcat ca rezolvat.'));
    }
}
