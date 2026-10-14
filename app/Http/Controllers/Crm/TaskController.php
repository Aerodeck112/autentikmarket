<?php

namespace App\Http\Controllers\Crm;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->input('view', 'open');
        $today = now()->toDateString();
        $tasks = Task::with(['event', 'assignee'])
            ->when($request->input('event'), fn ($q, $e) => $q->where('event_id', $e))
            ->when($view === 'open', fn ($q) => $q->open())
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($view === 'today', fn ($q) => $q->open()->whereDate('due_date', $today))
            ->when($view === 'week', fn ($q) => $q->open()->whereDate('due_date', '>', $today)->whereDate('due_date', '<=', now()->addDays(7)->toDateString()))
            ->when($view === 'done', fn ($q) => $q->where('status', TaskStatus::Done->value))
            ->when($view === 'mine', fn ($q) => $q->open()->where('assigned_to', $request->user()->id))
            ->orderByRaw('due_date is null')->orderBy('due_date')
            ->paginate(50)->withQueryString();

        return view('crm.tasks.index', [
            'tasks' => $tasks,
            'view' => $view,
            'events' => Event::orderByDesc('event_date')->limit(20)->get(),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['staff', 'organizer', 'admin', 'super_admin']))->orderBy('first_name')->get(),
            'counts' => [
                'overdue' => Task::overdue()->count(),
                'today' => Task::open()->whereDate('due_date', $today)->count(),
                'week' => Task::open()->whereDate('due_date', '>', $today)->whereDate('due_date', '<=', now()->addDays(7)->toDateString())->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_ro' => ['required', 'string', 'max:200'],
            'title_hu' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'event_id' => ['nullable', 'exists:events,id'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
        ]);
        Task::create($data + ['status' => TaskStatus::Open, 'phase' => 'general']);

        return back()->with('success', __('Task-ul a fost adăugat.'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
        ]);
        $data = array_filter($data, fn ($v) => $v !== null);
        if (($data['status'] ?? null) === TaskStatus::Done->value && $task->status !== TaskStatus::Done) {
            $data['completed_at'] = now();
            $data['completed_by'] = $request->user()->id;
        }
        $task->update($data);

        return back()->with('success', __('Task actualizat.'));
    }
}
