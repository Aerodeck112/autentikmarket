<?php

namespace App\Http\Controllers\Crm;

use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Task template engine: nimic hardcodat — deadline = event_date + relative_days. */
class TaskTemplateController extends Controller
{
    public function index(): View
    {
        return view('crm.tasks.templates', ['templates' => TaskTemplate::orderBy('phase')->orderBy('relative_days')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        TaskTemplate::create($this->validated($request));

        return back()->with('success', __('Template-ul a fost adăugat.'));
    }

    public function update(Request $request, TaskTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return back()->with('success', __('Template-ul a fost actualizat.'));
    }

    protected function validated(Request $request, ?TaskTemplate $template = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_]+$/', Rule::unique('task_templates', 'key')->ignore($template?->id)],
            'title_ro' => ['required', 'string', 'max:200'],
            'title_hu' => ['required', 'string', 'max:200'],
            'phase' => ['required', 'in:pre_event,post_event'],
            'relative_days' => ['required', 'integer', 'between:-365,365'],
            'responsible_role' => ['nullable', 'in:staff,organizer,admin,super_admin'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
