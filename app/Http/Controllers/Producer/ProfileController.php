<?php

namespace App\Http\Controllers\Producer;

use App\Enums\ProducerStatus;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\ProducerCategory;
use App\Services\Producers\ProducerFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('producer.profile', [
            'producer' => $request->user()->producer->load('gallery'),
            'categories' => ProducerCategory::where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $producer = $request->user()->producer;
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'cui' => ['nullable', 'string', 'max:30', 'regex:/^(RO)?\s?[0-9]{2,10}$/i'],
            'trade_register_no' => ['nullable', 'string', 'max:50'],
            'representative_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'iban' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9 ]{10,32}$/i'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'producer_category_id' => ['nullable', 'exists:producer_categories,id'],
            'description_ro' => ['nullable', 'string', 'max:3000'],
            'description_hu' => ['nullable', 'string', 'max:3000'],
            'story_ro' => ['nullable', 'string', 'max:6000'],
            'story_hu' => ['nullable', 'string', 'max:6000'],
            'products_ro' => ['nullable', 'string', 'max:3000'],
            'products_hu' => ['nullable', 'string', 'max:3000'],
        ]);
        if (isset($data['iban'])) {
            $data['iban'] = strtoupper(str_replace(' ', '', $data['iban']));
        }
        if ($data['brand_name'] !== $producer->brand_name) {
            $data['slug'] = $producer::uniqueSlug($data['brand_name'], $producer->id);
        }
        // Limba producătorului NU se schimbă de aici (o administrează CRM-ul / aleasă la înregistrare).
        $producer->update($data);

        if ($producer->status === ProducerStatus::New) {
            $producer->forceFill(['status' => ProducerStatus::Incomplete, 'status_changed_at' => now()])->save();
        }

        return back()->with('success', __('Profilul a fost salvat.'));
    }

    public function uploadMedia(Request $request, ProducerFiles $files): RedirectResponse
    {
        $request->validate(ProducerFiles::imageRules());
        $files->storeImage($request->user()->producer, $request->input('kind'), $request->file('file'), $request->user());

        return back()->with('success', __('Imaginea a fost încărcată.'));
    }

    public function deleteMedia(Request $request, Media $media): RedirectResponse
    {
        $producer = $request->user()->producer;
        // Izolare: producătorul poate șterge doar media proprie.
        abort_unless($media->mediable_type === $producer->getMorphClass() && (int) $media->mediable_id === $producer->id, 403);
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->thumb_path]));
        $media->delete();

        return back()->with('success', __('Imaginea a fost ștearsă.'));
    }
}
