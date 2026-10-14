<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ProducerCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('crm.categories.index', ['categories' => ProducerCategory::withCount('producers')->orderBy('sort_order')->get(), 'editing' => null]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('crm.categories.index');
    }

    public function store(Request $request): RedirectResponse
    {
        ProducerCategory::create($this->validated($request) + ['slug' => Str::slug($request->input('name_ro'))]);

        return back()->with('success', __('Categoria a fost adăugată.'));
    }

    public function edit(ProducerCategory $category): View
    {
        return view('crm.categories.index', ['categories' => ProducerCategory::withCount('producers')->orderBy('sort_order')->get(), 'editing' => $category]);
    }

    public function update(Request $request, ProducerCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('crm.categories.index')->with('success', __('Categoria a fost actualizată.'));
    }

    public function destroy(ProducerCategory $category): RedirectResponse
    {
        if ($category->producers()->exists()) {
            return back()->with('error', __('Categoria are producători asociați. Dezactiveaz-o în loc să o ștergi.'));
        }
        $category->delete();

        return back()->with('success', __('Categoria a fost ștearsă.'));
    }

    protected function validated(Request $request, ?ProducerCategory $category = null): array
    {
        $data = $request->validate([
            'name_ro' => ['required', 'string', 'max:120', Rule::unique('producer_categories', 'name_ro')->ignore($category?->id)],
            'name_hu' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
