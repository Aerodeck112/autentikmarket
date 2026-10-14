<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Annex;
use App\Models\Contract;
use App\Models\Producer;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q'));

        return view('crm.contracts.index', [
            'contracts' => Contract::with(['producer', 'generator'])
                ->when($q, fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhereHas('producer', fn ($p) => $p->where('brand_name', 'like', "%{$q}%")))
                ->latest('generated_at')->paginate(25, ['*'], 'cpage')->withQueryString(),
            'annexes' => Annex::with(['producer', 'event'])->latest('generated_at')->paginate(25, ['*'], 'apage')->withQueryString(),
            'q' => $q,
        ]);
    }

    public function generate(Request $request, Producer $producer, DocumentService $documents): RedirectResponse
    {
        $contract = $documents->generateContract($producer, $request->user());

        return back()->with('success', __('Contractul :n a fost generat.', ['n' => $contract->number]));
    }

    public function status(Request $request, Contract $contract): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:generated,sent,signed,cancelled'],
            'signed_file' => ['nullable', 'file', 'mimes:pdf', 'max:'.config('autentik.uploads.document_max_kb')],
        ]);
        $old = $contract->status;
        $contract->status = $request->input('status');
        if ($contract->status === 'signed') {
            $contract->signed_at ??= now();
        }
        if ($request->hasFile('signed_file')) {
            $contract->signed_path = Uploads::storePrivate($request->file('signed_file'), "contracts/{$contract->producer_id}/signed", ['pdf']);
        }
        $contract->save();
        AuditLogger::log('contract.status_changed', $contract, ['status' => $old], ['status' => $contract->status]);

        return back()->with('success', __('Contractul a fost actualizat.'));
    }
}
