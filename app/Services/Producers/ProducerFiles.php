<?php

namespace App\Services\Producers;

use App\Enums\DocumentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Media;
use App\Models\Producer;
use App\Models\ProducerDocument;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Imaginile (logo, copertă, galerie) și documentele unui producător — încărcate de el din portal
 * sau de organizator din CRM, cu aceleași reguli.
 */
class ProducerFiles
{
    public const GALLERY_MAX = 12;

    public const DOCUMENTS_MAX = 40;

    public static function imageRules(): array
    {
        return [
            'kind' => ['required', 'in:logo,cover,gallery'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('autentik.uploads.image_max_kb'), 'dimensions:min_width=200,min_height=200'],
        ];
    }

    public static function documentRules(): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'string', 'max:200'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config('autentik.uploads.document_max_kb')],
        ];
    }

    public function storeImage(Producer $producer, string $kind, UploadedFile $file, User $by): void
    {
        if ($kind === 'gallery' && $producer->gallery()->count() >= self::GALLERY_MAX) {
            throw new BusinessRuleException(__('Galeria poate avea maximum 12 fotografii.'), 'gallery_full');
        }
        $path = Uploads::storeImage($file, "producers/{$producer->id}");

        if ($kind === 'gallery') {
            $size = @getimagesize(Storage::disk('public')->path($path)) ?: [null, null];
            $producer->gallery()->create([
                'collection' => 'gallery', 'disk' => 'public', 'path' => $path,
                'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                'width' => $size[0], 'height' => $size[1], 'uploaded_by' => $by->id,
                'sort_order' => $producer->gallery()->count() + 1,
            ]);

            return;
        }
        $column = $kind.'_path';
        if ($producer->$column) {
            Storage::disk('public')->delete($producer->$column);
        }
        $producer->forceFill([$column => $path])->save();
    }

    /** Șterge o fotografie din galerie sau logo-ul / coperta. */
    public function deleteImage(Producer $producer, string $kindOrMediaId): void
    {
        if (in_array($kindOrMediaId, ['logo', 'cover'], true)) {
            $column = $kindOrMediaId.'_path';
            if ($producer->$column) {
                Storage::disk('public')->delete($producer->$column);
                $producer->forceFill([$column => null])->save();
            }

            return;
        }
        $media = Media::findOrFail((int) $kindOrMediaId);
        abort_unless($media->mediable_type === $producer->getMorphClass() && (int) $media->mediable_id === $producer->id, 403);
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->thumb_path]));
        $media->delete();
    }

    /** @param  array{type: string, title?: ?string, expires_at?: ?string}  $data */
    public function storeDocument(Producer $producer, array $data, UploadedFile $file, ?User $acceptedBy = null): ProducerDocument
    {
        if ($producer->documents()->count() >= self::DOCUMENTS_MAX) {
            throw new BusinessRuleException(__('Ai atins numărul maxim de documente.'), 'documents_full');
        }
        $type = DocumentType::from($data['type']);
        $document = $producer->documents()->create([
            'type' => $type,
            'title' => ($data['title'] ?? null) ?: $type->label(),
            'path' => Uploads::storePrivate($file, "producer-documents/{$producer->id}"),
            'original_name' => Uploads::cleanName($file->getClientOriginalName()),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        // Încărcat de organizator din CRM: îl are în față, deci e verificat.
        if ($acceptedBy) {
            $document->forceFill(['status' => 'accepted', 'reviewed_by' => $acceptedBy->id, 'reviewed_at' => now()])->save();
        }

        return $document;
    }

    public function deleteDocument(ProducerDocument $document): void
    {
        Storage::disk('private')->delete($document->path);
        $document->delete();
    }
}
