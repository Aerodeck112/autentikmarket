<?php

namespace App\Http\Controllers\Crm;

use App\Enums\UpdateStatus;
use App\Http\Controllers\Controller;
use App\Models\ApplicationUpdate;
use App\Services\Updates\UpdateManager;
use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM → SISTEM → ACTUALIZĂRI. Accesibil DOAR cu permisiunea system.update (Super Admin implicit).
 */
class UpdateController extends Controller
{
    public function __construct(protected UpdateManager $manager) {}

    public function index(): View
    {
        return view('crm.system.updates', [
            'version' => AppVersion::current(),
            'pending' => ApplicationUpdate::whereIn('status', [UpdateStatus::Validated->value, UpdateStatus::BackupCreated->value, UpdateStatus::Installing->value])->latest('id')->first(),
            'history' => ApplicationUpdate::with(['installer', 'uploader', 'backup'])->latest('id')->paginate(20),
            'maxUpload' => min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size')),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream', 'max:'.(config('update.max_package_mb') * 1024)],
        ]);
        $file = $request->file('package');
        $update = $this->manager->receive($file->getRealPath(), $file->getClientOriginalName(), $request->user());

        return $update->status === UpdateStatus::Validated
            ? redirect()->route('crm.updates.show', $update)->with('success', __('Pachetul este valid. Verifică noutățile și pornește instalarea.'))
            : redirect()->route('crm.updates.index')->with('error', __('Pachet respins').': '.$update->message);
    }

    public function show(ApplicationUpdate $update): View
    {
        return view('crm.system.update-show', [
            'update' => $update->load(['backup', 'installer', 'uploader']),
            'version' => AppVersion::current(),
            'next' => $this->manager->nextStep($update),
        ]);
    }

    public function confirmDestructive(Request $request, ApplicationUpdate $update): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);
        $this->manager->confirmDestructive($update, $request->user());

        return back()->with('success', __('Confirmarea a fost înregistrată.'));
    }

    public function step(Request $request, ApplicationUpdate $update, string $step): JsonResponse
    {
        @set_time_limit(300);
        $result = $this->manager->runStep($update, $step, $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function rollback(Request $request, ApplicationUpdate $update): RedirectResponse
    {
        if (! in_array($update->status, [UpdateStatus::Failed, UpdateStatus::Completed], true)) {
            return back()->with('error', __('Rollback-ul este disponibil doar pentru actualizări finalizate sau eșuate.'));
        }
        $ok = $this->manager->rollback($update, $request->user());

        return back()->with($ok ? 'success' : 'error', $ok ? __('Fișierele au fost restaurate din backup.') : __('Rollback eșuat — vezi jurnalul actualizării.'));
    }

    protected function iniBytes(string $key): int
    {
        $v = trim((string) ini_get($key));
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
