<?php

namespace App\Http\Controllers\Crm;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\AuditLogger;
use App\Services\Notifications\TemplateRenderer;
use App\Support\Installer;
use App\Support\QueueRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(): View
    {
        return view('crm.automations.index', [
            'templates' => NotificationTemplate::orderBy('key')->get(),
            'stats' => NotificationLog::select('status', DB::raw('count(*) c'))->where('created_at', '>=', now()->subDays(30))->groupBy('status')->pluck('c', 'status'),
            'queue' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'lastRun' => QueueRunner::lastRun(),
            'heartbeat' => Cache::get('scheduler.heartbeat'),
            'cronInfo' => Cache::get(QueueRunner::HEARTBEAT_INFO_KEY),
            'cronCommand' => Installer::guessPhpCli().' '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
            'schedule' => [
                ['queue', __('în fiecare minut'), __('Procesare coadă e-mailuri')],
                ['reservations:expire-holds', __('în fiecare minut'), __('Eliberare standuri cu hold expirat')],
                ['events:auto-advance', __('orar'), __('Înscrieri deschise/închise, târg activ, încheiat')],
                ['events:post-event', __('orar'), __('Mulțumiri, recenzii, task-uri post-eveniment, draft blog')],
                ['events:send-reminders', '09:00', __('Remindere producători (D-3) și vizitatori (D-2)')],
                ['tasks:send-reminders', '08:00', __('Remindere task-uri organizatori')],
                ['loyalty:expire-rewards', '03:10', __('Recompense expirate')],
            ],
        ]);
    }

    public function edit(NotificationTemplate $template, TemplateRenderer $renderer): View
    {
        return view('crm.automations.edit', [
            'template' => $template,
            'placeholders' => array_values(array_unique(array_merge(
                ['name', 'app_name', 'app_url'],
                $renderer->placeholders($template->body_ro.$template->subject_ro.$template->body_hu)
            ))),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'subject_ro' => ['required', 'string', 'max:255'],
            'subject_hu' => ['required', 'string', 'max:255'],
            'body_ro' => ['required', 'string', 'max:20000'],
            'body_hu' => ['required', 'string', 'max:20000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        $template->update($data);
        AuditLogger::log('notification_template.updated', $template, [], ['key' => $template->key, 'active' => $template->active]);

        return back()->with('success', __('Template-ul a fost salvat.'));
    }

    public function log(Request $request): View
    {
        return view('crm.automations.log', [
            'logs' => NotificationLog::query()
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->input('key'), fn ($q, $k) => $q->where('template_key', $k))
                ->when($request->input('language'), fn ($q, $l) => $q->where('language', $l))
                ->latest('id')->paginate(50)->withQueryString(),
            'keys' => NotificationTemplate::orderBy('key')->pluck('key')->unique(),
        ]);
    }

    public function retry(NotificationLog $log): RedirectResponse
    {
        if ($log->status !== NotificationStatus::Failed) {
            return back()->with('error', __('Doar mesajele eșuate pot fi retrimise.'));
        }
        $log->update(['status' => NotificationStatus::Queued, 'error' => null]);
        SendNotificationJob::dispatch($log->id);

        return back()->with('success', __('Mesajul a fost repus în coadă.'));
    }

    /** „Trimite acum”: golește coada din CRM (în același proces), fără să aștepte cron-ul. */
    public function runQueue(): RedirectResponse
    {
        $r = QueueRunner::run(20, 'crm');
        AuditLogger::log('queue.run_manually', null, [], $r);
        if ($r['error']) {
            return back()->with('error', __('Procesarea cozii a eșuat: :m', ['m' => $r['error']]));
        }
        $msg = __('Procesate acum: :p. Rămase în coadă: :r.', ['p' => $r['processed'], 'r' => $r['remaining']]);
        if ($r['remaining'] > 0) {
            $msg .= ' '.__('Mesajele care au dat eroare se reîncearcă automat după 1–5 minute; motivul apare în Jurnal notificări.');
        }

        return back()->with('success', $msg);
    }

    /** Joburile eșuate definitiv (după 3 încercări) sunt puse înapoi în coadă. */
    public function retryFailed(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => ['all']]);
        AuditLogger::log('queue.retry_failed', null, [], ['count' => $count]);

        return back()->with('success', __(':n joburi eșuate au fost puse înapoi în coadă.', ['n' => $count]));
    }
}
