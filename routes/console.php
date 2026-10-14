<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Laravel Scheduler — TOATE automatizările
|--------------------------------------------------------------------------
| Pe cPanel există UN SINGUR cron (vezi docs/CRON.md):
|   * * * * *  /usr/local/bin/php /home/USER/autentikmarket/artisan schedule:run >> /dev/null 2>&1
*/

// Coada de joburi (QUEUE_CONNECTION=database) — fără Supervisor.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --sleep=1')
    ->everyMinute()->withoutOverlapping(5)->name('queue-processing');

// Rezervări: hold-uri expirate (fără WebSocket).
Schedule::command('reservations:expire-holds')->everyMinute()->withoutOverlapping()->name('expire-holds');

// Ciclul de viață al evenimentelor (înscrieri, activ, încheiat → post-event).
Schedule::command('events:auto-advance')->hourly()->withoutOverlapping()->name('events-auto-advance');

// Plasă de siguranță pentru workflow-ul post-eveniment (mulțumiri, recenzii, task-uri, blog draft).
Schedule::command('events:post-event')->hourlyAt(15)->withoutOverlapping()->name('post-event');

// Remindere eveniment (producători D-3, vizitatori D-2) — dimineața.
Schedule::command('events:send-reminders')->dailyAt('09:00')->withoutOverlapping()->name('event-reminders');

// Remindere task-uri organizatori.
Schedule::command('tasks:send-reminders')->dailyAt('08:00')->withoutOverlapping()->name('task-reminders');

// Fidelitate: recompense expirate.
Schedule::command('loyalty:expire-rewards')->dailyAt('03:10')->name('expire-rewards');

// Mentenanță.
Schedule::command('queue:prune-failed --hours=720')->weekly()->name('prune-failed-jobs');
Schedule::command('auth:clear-resets')->daily()->name('clear-password-resets');

// Heartbeat: CRM → Sistem afișează când a rulat ultima dată cron-ul.
Schedule::call(fn () => Cache::put('scheduler.heartbeat', now()->format('d.m.Y H:i'), 86400))
    ->everyMinute()->name('scheduler-heartbeat');
