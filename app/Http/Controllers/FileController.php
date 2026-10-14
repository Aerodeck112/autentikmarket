<?php

namespace App\Http\Controllers;

use App\Models\Annex;
use App\Models\Contract;
use App\Models\ProducerDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fișiere PRIVATE: verificare server-side a dreptului de acces.
 * Producătorul A NU poate accesa documentele producătorului B.
 */
class FileController extends Controller
{
    public function producerDocument(Request $request, ProducerDocument $document): StreamedResponse
    {
        $this->authorizeProducerFile($request->user(), $document->producer_id, 'producers.view');

        return $this->stream($document->path, $document->original_name, $document->mime);
    }

    public function contract(Request $request, Contract $contract): StreamedResponse
    {
        $this->authorizeProducerFile($request->user(), $contract->producer_id, 'contracts.manage');

        return $this->stream($contract->signed_path ?: $contract->path, 'contract-'.$contract->number.'.pdf', 'application/pdf');
    }

    public function annex(Request $request, Annex $annex): StreamedResponse
    {
        $this->authorizeProducerFile($request->user(), $annex->producer_id, 'contracts.manage');

        return $this->stream($annex->path, 'anexa-'.$annex->number.'.pdf', 'application/pdf');
    }

    protected function authorizeProducerFile(User $user, int $producerId, string $staffPermission): void
    {
        $isOwner = $user->producer && $user->producer->id === $producerId;
        abort_unless($isOwner || $user->hasPermission($staffPermission) || $user->isSuperAdmin(), 403);
    }

    protected function stream(string $path, string $name, string $mime): StreamedResponse
    {
        abort_unless(Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
