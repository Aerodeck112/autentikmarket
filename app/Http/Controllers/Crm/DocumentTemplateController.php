<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        return view('crm.contracts.templates', ['templates' => DocumentTemplate::orderBy('type')->orderBy('language')->get()]);
    }

    public function edit(DocumentTemplate $template): View
    {
        return view('crm.contracts.template-edit', [
            'template' => $template,
            'placeholders' => $template->type === 'contract' ? DocumentService::CONTRACT_PLACEHOLDERS : DocumentService::ANNEX_PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'body_html' => ['required', 'string', 'max:200000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        // Fără scripturi/evenimente în template (PDF-ul nu le execută, dar le eliminăm oricum).
        $data['body_html'] = preg_replace(['~<script\b[^>]*>.*?</script>~is', '~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i'], '', $data['body_html']);
        $data['is_active'] = $request->boolean('is_active');
        $template->update($data + ['version' => $template->version + 1, 'updated_by' => $request->user()->id]);
        AuditLogger::log('document_template.updated', $template, [], ['version' => $template->version]);

        return back()->with('success', __('Template-ul a fost salvat (versiunea :v).', ['v' => $template->version]));
    }

    public function preview(DocumentTemplate $template, DocumentService $documents): Response
    {
        $html = view('pdf.document', ['title' => $template->name, 'body' => $documents->previewHtml($template), 'lang' => $template->language])->render();

        return response($html)->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
    }
}
