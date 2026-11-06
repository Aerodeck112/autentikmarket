<?php

namespace App\Http\Controllers\Producer;

use App\Enums\ProducerStatus;
use App\Http\Controllers\Controller;
use App\Models\ProducerDocument;
use App\Services\Producers\ProducerFiles;
use App\Services\Producers\ProducerWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request, ProducerWorkflow $workflow): View
    {
        $producer = $request->user()->producer;

        return view('producer.documents', [
            'producer' => $producer,
            'documents' => $producer->documents()->latest()->get(),
            'requests' => $producer->documentRequests()->latest()->get(),
            'contracts' => $producer->contracts()->latest('generated_at')->get(),
            'annexes' => $producer->annexes()->with('event')->latest('generated_at')->get(),
            'missing' => $workflow->missingFields($producer),
        ]);
    }

    public function store(Request $request, ProducerFiles $files): RedirectResponse
    {
        $data = $request->validate(ProducerFiles::documentRules());
        $files->storeDocument($request->user()->producer, $data, $request->file('file'));

        return back()->with('success', __('Documentul a fost încărcat.'));
    }

    public function destroy(Request $request, ProducerDocument $document): RedirectResponse
    {
        $producer = $request->user()->producer;
        abort_unless($document->producer_id === $producer->id, 403);
        if ($document->status === 'accepted' && $producer->status === ProducerStatus::Approved) {
            return back()->with('error', __('Documentele acceptate nu pot fi șterse după aprobare. Contactează organizatorii.'));
        }
        app(ProducerFiles::class)->deleteDocument($document);

        return back()->with('success', __('Documentul a fost șters.'));
    }

    public function submit(Request $request, ProducerWorkflow $workflow): RedirectResponse
    {
        $workflow->submit($request->user()->producer, $request->user());

        return back()->with('success', __('Profilul a fost trimis spre verificare. Te anunțăm pe e-mail.'));
    }
}
