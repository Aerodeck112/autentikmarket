<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

/**
 * Reminder zilnic pentru task-urile cu termen azi sau depășit — către persoana asignată
 * sau, dacă nu există, către utilizatorii cu rolul responsabil.
 */
class SendTaskRemindersCommand extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Trimite remindere pentru task-urile organizatorilor (azi + întârziate).';

    public function handle(NotificationService $notifications): int
    {
        $tasks = Task::open()->with(['event', 'assignee'])
            ->whereDate('due_date', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('reminder_sent_at')->orWhereDate('reminder_sent_at', '<', now()->toDateString()))
            ->get();

        $sent = 0;
        foreach ($tasks as $task) {
            $recipients = $task->assignee
                ? collect([$task->assignee])
                : User::whereHas('roles', fn ($q) => $q->where('name', $task->responsible_role ?: 'organizer'))->where('is_active', true)->get();

            foreach ($recipients as $user) {
                $lang = $user->preferredLanguage()->value;
                $sent += (int) (bool) $notifications->send('TASK_REMINDER', $user, [
                    'task_title' => $task->t('title', $lang),
                    'due_date' => format_date_local($task->due_date, false, $lang),
                    'event_title' => (string) $task->event?->t('title', $lang),
                    'overdue' => $task->isOverdue() ? ($lang === 'hu' ? 'LEJÁRT' : 'ÎNTÂRZIAT') : '',
                ], [
                    'related' => $task,
                    'dedupe_key' => "task-reminder:{$task->id}:{$user->id}:".now()->toDateString(),
                    'action_url' => route('crm.tasks.index'),
                    'action_label_ro' => 'Deschide task-urile',
                    'action_label_hu' => 'Feladatok megnyitása',
                ]);
            }
            $task->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info("Remindere task: {$sent}");

        return self::SUCCESS;
    }
}
