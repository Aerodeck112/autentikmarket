<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use App\Support\MailSettings;
use App\Support\Settings;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('crm.settings.index', [
            'mail' => [
                'from_crm' => MailSettings::fromCrm(),
                'delivers' => MailSettings::delivers(),
                'mailer' => config('mail.default'),
                'host' => Settings::get('mail.smtp_host') ?: config('mail.mailers.smtp.host'),
                'port' => Settings::get('mail.smtp_port') ?: config('mail.mailers.smtp.port'),
                'encryption' => Settings::get('mail.smtp_encryption') ?: ((int) config('mail.mailers.smtp.port') === 587 ? 'tls' : 'ssl'),
                'username' => Settings::get('mail.smtp_username') ?: config('mail.mailers.smtp.username'),
                'has_password' => (string) Settings::get('mail.smtp_password', '') !== '',
                'verify_peer' => Settings::get('mail.smtp_verify_peer', '1') !== '0',
                'from_address' => Settings::get('mail.from_address') ?: config('mail.from.address'),
                'from_name' => Settings::get('mail.from_name') ?: config('mail.from.name'),
                'queued' => DB::table('jobs')->count(),
                'heartbeat' => Cache::get('scheduler.heartbeat'),
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

    /**
     * Setări → E-mail (SMTP): salvare în CRM (fără .env) și, opțional, test imediat.
     * Parola goală = se păstrează cea salvată.
     */
    public function updateMail(Request $request): RedirectResponse
    {
        if ($request->input('action') === 'clear') {
            foreach (['mail.smtp_host', 'mail.smtp_port', 'mail.smtp_encryption', 'mail.smtp_username', 'mail.smtp_password', 'mail.smtp_verify_peer', 'mail.from_address', 'mail.from_name'] as $key) {
                Settings::set($key, '', 'mail');
            }
            AuditLogger::log('settings.mail_cleared');

            return back()->with('success', __('Setările SMTP din CRM au fost șterse; se folosesc valorile din .env.'));
        }

        $data = $request->validate([
            'smtp_host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', 'in:'.implode(',', MailSettings::ENCRYPTIONS)],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
            'smtp_verify_peer' => ['nullable', 'boolean'],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ], ['smtp_host.regex' => __('Scrie doar numele serverului, fără https:// sau spații (de ex. mail.autentikmarket.ro).')]);

        Settings::setMany([
            'mail.smtp_host' => strtolower($data['smtp_host']),
            'mail.smtp_port' => (string) $data['smtp_port'],
            'mail.smtp_encryption' => $data['smtp_encryption'],
            'mail.smtp_username' => trim((string) ($data['smtp_username'] ?? '')),
            'mail.smtp_verify_peer' => $request->boolean('smtp_verify_peer') ? '1' : '0',
            'mail.from_address' => trim((string) ($data['from_address'] ?? '')),
            'mail.from_name' => trim((string) ($data['from_name'] ?? '')),
        ], 'mail');
        if (($data['smtp_password'] ?? '') !== '') {
            Settings::set('mail.smtp_password', MailSettings::encryptPassword($data['smtp_password']), 'mail');
        }
        MailSettings::apply();
        AuditLogger::log('settings.mail_updated', null, [], ['host' => $data['smtp_host'], 'port' => $data['smtp_port'], 'encryption' => $data['smtp_encryption']]);

        if ($request->input('action') === 'test') {
            return $this->sendTest($request);
        }

        return back()->with('success', __('Setările SMTP au fost salvate. Trimite un e-mail de test ca să verifici.'));
    }

    /** „Trimite email test”: trimis IMEDIAT (nu prin coadă), ca eroarea serverului să apară pe loc. */
    public function testEmail(Request $request): RedirectResponse
    {
        return $this->sendTest($request);
    }

    protected function sendTest(Request $request): RedirectResponse
    {
        $to = $request->user()->email;
        if (! MailSettings::delivers()) {
            return back()->with('error', __('E-mailurile nu sunt trimise: aplicația este în modul „:m” (doar scrie mesajele în log). Completează datele SMTP mai jos și salvează.', ['m' => config('mail.default')]));
        }

        try {
            $subject = __('Test e-mail Autentik Market');
            $html = view('mail.templated', [
                'subject' => $subject,
                'bodyHtml' => '<p>'.e(__('Dacă primești acest mesaj, trimiterea e-mailurilor funcționează.')).'</p><p>'.e(__('Server: :h, port :p.', ['h' => config('mail.mailers.smtp.host'), 'p' => config('mail.mailers.smtp.port')])).'</p>',
                'actionUrl' => null,
                'actionLabel' => null,
                'lang' => app()->getLocale(),
            ])->render();
            Mail::mailer(config('mail.default'))->to($to)->send(new TemplatedMail($subject, $html, app()->getLocale()));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('E-mailul de test NU a putut fi trimis.').' '.MailSettings::explain($e));
        }
        AuditLogger::log('settings.mail_tested', null, [], ['to' => $to]);

        return back()->with('success', __('E-mailul de test a fost trimis către :e. Verifică inboxul (și folderul Spam).', ['e' => $to]));
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
