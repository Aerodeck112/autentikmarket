<?php

use App\Support\QueueRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Laravel Scheduler — TOATE automatizările
|--------------------------------------------------------------------------
| Pe cPanel există UN SINGUR cron (vezi docs/CRON.md):
|   * * * * *  /usr/local/bin/php /home/USER/public_html/artisan schedule:run >> /dev/null 2>&1
|
| Toate sarcinile rulează ÎN ACELAȘI PROCES cu schedule:run (Schedule::call + Artisan::call), nu ca
| procese separate (Schedule::command folosește proc_open, des dezactivat pe hostingul partajat: cron-ul
| părea că merge, dar coada de e-mailuri nu era procesată niciodată).
*/

// Heartbeat: CRM → Sistem afișează când a rulat ultima dată cron-ul și cu ce PHP.
Schedule::call(fn () => QueueRunner::heartbeat())->everyMinute()->name('scheduler-heartbeat');

// Rezervări: hold-uri expirate (fără WebSocket).
Schedule::call(fn () => Artisan::call('reservations:expire-holds'))->everyMinute()->name('expire-holds')->withoutOverlapping();

// Ciclul de viață al evenimentelor (înscrieri, activ, încheiat → post-event).
Schedule::call(fn () => Artisan::call('events:auto-advance'))->hourly()->name('events-auto-advance')->withoutOverlapping();

// Plasă de siguranță pentru workflow-ul post-eveniment (mulțumiri, recenzii, task-uri, blog draft).
Schedule::call(fn () => Artisan::call('events:post-event'))->hourlyAt(15)->name('post-event')->withoutOverlapping();

// Remindere eveniment (producători D-3, vizitatori D-2) — dimineața.
Schedule::call(fn () => Artisan::call('events:send-reminders'))->dailyAt('09:00')->name('event-reminders')->withoutOverlapping();

// Remindere task-uri organizatori.
Schedule::call(fn () => Artisan::call('tasks:send-reminders'))->dailyAt('08:00')->name('task-reminders')->withoutOverlapping();

// Fidelitate: recompense expirate.
Schedule::call(fn () => Artisan::call('loyalty:expire-rewards'))->dailyAt('03:10')->name('expire-rewards');

// Mentenanță.
Schedule::call(fn () => Artisan::call('queue:prune-failed', ['--hours' => 720]))->weekly()->name('prune-failed-jobs');
Schedule::call(fn () => Artisan::call('auth:clear-resets'))->daily()->name('clear-password-resets');

// Coada de joburi (QUEUE_CONNECTION=database) — fără Supervisor. Ultima: rulează până la 45 s.
Schedule::call(fn () => QueueRunner::run(45))->everyMinute()->name('queue-processing')->withoutOverlapping(5);
