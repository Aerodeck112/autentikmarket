<?php

namespace App\Http\Controllers\Producer;

use App\Enums\DocumentType;
use App\Enums\ProducerStatus;
use App\Http\Controllers\Controller;
use App\Models\ProducerDocument;
use App\Services\Producers\ProducerWorkflow;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
    {
        $producer = $request->user()->producer;
        $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'string', 'max:200'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config('autentik.uploads.document_max_kb')],
        ]);
        if ($producer->documents()->count() >= 40) {
            return back()->with('error', __('Ai atins numărul maxim de documente.'));
        }

        $file = $request->file('file');
        $path = Uploads::storePrivate($file, "producer-documents/{$producer->id}");
        $type = DocumentType::from($request->input('type'));

        $producer->documents()->create([
            'type' => $type,
            'title' => $request->input('title') ?: $type->label(),
            'path' => $path,
            'original_name' => Uploads::cleanName($file->getClientOriginalName()),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'expires_at' => $request->input('expires_at'),
        ]);

        return back()->with('success', __('Documentul a fost încărcat.'));
    }

    public function destroy(Request $request, ProducerDocument $document): RedirectResponse
    {
        $producer = $request->user()->producer;
        abort_unless($document->producer_id === $producer->id, 403);
        if ($document->status === 'accepted' && $producer->status === ProducerStatus::Approved) {
            return back()->with('error', __('Documentele acceptate nu pot fi șterse după aprobare. Contactează organizatorii.'));
        }
        Storage::disk('private')->delete($document->path);
        $document->delete();

        return back()->with('success', __('Documentul a fost șters.'));
    }

    public function submit(Request $request, ProducerWorkflow $workflow): RedirectResponse
    {
        $workflow->submit($request->user()->producer, $request->user());

        return back()->with('success', __('Profilul a fost trimis spre verificare. Te anunțăm pe e-mail.'));
    }
}
