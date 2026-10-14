# CRON: automatizări pe cPanel

## Un singur cron

```bash
* * * * *  /usr/local/bin/php /home/USER/autentikmarket/artisan schedule:run >> /dev/null 2>&1
```

Laravel Scheduler (`routes/console.php`) decide ce rulează și când. Ultima rulare apare în CRM → Sistem („Ultimul cron”).

Dacă hostingul permite cron cel mai des la 5 minute, setați `*/5 * * * *`. Totul funcționează, cu întârzieri de până la 5 minute: e-mailurile pleacă mai târziu, iar hold-urile expirate sunt eliberate oricum și la fiecare request pe hartă.

## Ce rulează

| Comandă | Frecvență | Rol |
|---|---|---|
| `queue:work --stop-when-empty --max-time=50` | fiecare minut, `withoutOverlapping` | procesează coada `database` (e-mailuri, workflow post-eveniment) **fără Supervisor** |
| `reservations:expire-holds` | fiecare minut | eliberează standurile cu hold expirat (fără WebSocket) |
| `events:auto-advance` | orar | deschide/închide înscrierile după date, marchează târgul activ în ziua lui și încheiat a doua zi (pornește post-event) |
| `events:post-event` | orar (:15) | plasă de siguranță: mulțumiri RO/HU, cereri recenzie, task-uri post, draft blog |
| `events:send-reminders` | zilnic 09:00 | remindere producători (D-3) și vizitatori cu acord (D-2) |
| `tasks:send-reminders` | zilnic 08:00 | remindere task-uri (azi + întârziate) |
| `loyalty:expire-rewards` | zilnic 03:10 | recompense expirate |
| `queue:prune-failed`, `auth:clear-resets` | săptămânal / zilnic | mentenanță |

## De ce `queue:work --stop-when-empty`

- Pe cPanel nu există procese permanente (fără Supervisor).
- Workerul pornește la fiecare minut, procesează tot ce e în coadă (maxim 50 s) și se oprește.
- `withoutOverlapping` previne rularea a două workere simultan.
- Joburile eșuate se reîncearcă de 3 ori (backoff 60 s / 300 s), apoi apar în CRM → Automatizări (cu buton „Retrimite”).

## Rulare manuală (Terminal)

```bash
php artisan schedule:list          # programul
php artisan schedule:run           # o rulare
php artisan queue:work --stop-when-empty
php artisan events:auto-advance
```
