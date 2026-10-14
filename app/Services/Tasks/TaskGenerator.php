<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskTemplate;

/**
 * Task engine: checklist-urile se generează din template-uri (nimic hardcodat).
 * Deadline = data evenimentului + relative_days (negativ = înainte).
 */
class TaskGenerator
{
    public function generateForEvent(Event $event, string $phase = 'pre_event'): int
    {
        $created = 0;
        $templates = TaskTemplate::where('active', true)->where('phase', $phase)->orderBy('sort_order')->get();

        foreach ($templates as $tpl) {
            $task = Task::firstOrCreate(
                ['event_id' => $event->id, 'task_template_id' => $tpl->id],
                [
                    'title_ro' => $tpl->title_ro,
                    'title_hu' => $tpl->title_hu,
                    'description' => $tpl->description_ro,
                    'phase' => $phase,
                    'due_date' => $event->event_date->copy()->addDays($tpl->relative_days),
                    'responsible_role' => $tpl->responsible_role,
                    'priority' => $tpl->priority,
                    'status' => TaskStatus::Open,
                ]
            );
            $created += $task->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /** Recalculează termenele task-urilor deschise când se mută data evenimentului. */
    public function rescheduleForEvent(Event $event): void
    {
        $event->tasks()->open()->whereNotNull('task_template_id')->with('template')->get()
            ->each(function (Task $task) use ($event) {
                if ($task->template) {
                    $task->update(['due_date' => $event->event_date->copy()->addDays($task->template->relative_days)]);
                }
            });
    }
}
