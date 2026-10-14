<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ApplicationUpdate;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\AppVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function index(): View
    {
        $dbOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        return view('crm.system.index', [
            'version' => AppVersion::current(),
            'checks' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                __('Bază de date') => DB::connection()->getDriverName().($dbOk ? ' ✓' : ' ✗'),
                __('Coadă') => config('queue.default'),
                'Cache' => config('cache.default'),
                __('Mediu') => app()->environment(),
                'Debug' => config('app.debug') ? 'ON ⚠' : 'OFF',
                __('Fus orar') => config('app.timezone'),
                __('Spațiu liber') => ($f = @disk_free_space(base_path())) ? round($f / 1073741824, 1).' GB' : '—',
                __('Ultimul cron (scheduler)') => Cache::get('scheduler.heartbeat') ?? '—',
                __('Joburi în coadă') => DB::table('jobs')->count(),
                __('Joburi eșuate') => DB::table('failed_jobs')->count(),
            ],
            'lastUpdate' => ApplicationUpdate::where('status', 'completed')->latest('finished_at')->first(),
        ]);
    }

    /**
     * Reîncarcă .env + cache-urile (config, rute, view-uri) — echivalentul `php artisan optimize:clear && optimize`,
     * pentru găzduirile fără Terminal (ex. după modificarea .env din File Manager).
     */
    public function refreshConfig(): RedirectResponse
    {
        try {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Reîncărcarea configurației a eșuat: :m', ['m' => $e->getMessage()]));
        }
        AuditLogger::log('system.config_refreshed');

        return back()->with('success', __('Configurația a fost reîncărcată (inclusiv modificările din .env).'));
    }

    public function audit(Request $request): View
    {
        return view('crm.system.audit', [
            'logs' => AuditLog::with('user')
                ->when($request->input('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->latest('id')->paginate(50)->withQueryString(),
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
