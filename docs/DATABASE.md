# DATABASE: schema bazei de date

MySQL 8 / MariaDB ≥ 10.6, `utf8mb4_unicode_ci`. Migrații în `database/migrations` (incrementale). Statusurile sunt stocate ca `string` (nu ENUM DB), ca să poată fi extinse fără `ALTER`. Valorile sunt definite în `app/Enums`.

## Diagramă (simplificată)

```
users ─┬─< role_user >─ roles ─< permission_role >─ permissions
       ├── producers ─┬─< producer_documents
       │              ├─< producer_document_requests
       │              ├─< producer_status_history
       │              ├─< contracts ─< annexes
       │              └─< media (gallery)
       ├─< checkins >──────────────── events ─┬── event_layouts ─< event_slots
       ├─< producer_customer_interactions >──┤                         │
       ├─< rewards ─── reward_redemptions     ├─< event_registrations   │
       ├─< raffle_entries >── raffles ────────┤        │                │
       │                       ├─< raffle_prizes       └─< stand_reservations >┘
       │                       └─< raffle_draws ─< raffle_winners
       ├─< reviews >── producers / events
       └─< consents
venues ─< venue_layouts ─< layout_slots          (master, editabil)
loyalty_rules ─< rewards
task_templates ─< tasks >── events
notification_templates · notification_logs (polimorfic: User | Producer | NewsletterSubscriber)
document_templates · blog_posts · media (polimorfic) · settings · audit_logs
newsletter_subscribers · contact_messages
application_updates ─── update_backups
sessions · cache · jobs · failed_jobs · password_reset_tokens
```

## Tabele și constrângeri esențiale

| Tabel | Rol | Constrângeri / indexuri importante |
|---|---|---|
| `users` | conturi (vizitatori, producători, staff, admin) | `email` UNIQUE, `member_code` UNIQUE (AM-XXXXXX), `card_token` UNIQUE (40 de caractere, în QR), `preferred_language` INDEX |
| `roles`, `permissions`, pivoturi | RBAC | `name` UNIQUE |
| `producers` | profil producător | `user_id` UNIQUE, `slug` UNIQUE, `qr_token` UNIQUE, INDEX (`status`, `preferred_language`) |
| `producer_documents` | documente private | fișier pe discul `private`, `uuid` folosit ca cheie de rută |
| `producer_status_history` | istoric verificare | cine, când, notă |
| `venues` / `venue_layouts` / `layout_slots` | locații + layout master | UNIQUE (`venue_layout_id`, `code`) |
| `events` | târguri | `slug` UNIQUE, INDEX (`status`, `event_date`) |
| `event_layouts` / `event_slots` | snapshot hartă per eveniment | `event_layouts.event_id` UNIQUE; UNIQUE (`event_id`, `code`) |
| `event_registrations` | participarea producătorului | UNIQUE (`event_id`, `producer_id`) |
| `stand_reservations` | hold / cerere / aprobare | **`active_slot_id` UNIQUE** (un singur rând activ per stand), **`active_producer_key` UNIQUE** (o rezervare activă per producător și eveniment) |
| `checkins` | vizite | **UNIQUE (`event_id`, `user_id`)**, deci o vizită per eveniment |
| `producer_customer_interactions` | client → producător → eveniment | UNIQUE (`event_id`, `producer_id`, `user_id`) |
| `loyalty_rules` | praguri configurabile | `threshold` UNIQUE |
| `rewards` | recompense acordate | UNIQUE (`user_id`, `loyalty_rule_id`) |
| `reward_redemptions` | ridicări | **`reward_id` UNIQUE**, deci o singură ridicare |
| `raffles`, `raffle_prizes`, `raffle_entries` | tombole | UNIQUE (`raffle_id`, `user_id`) la înscrieri |
| `raffle_draws` | extrageri imuabile | snapshot JSON + `snapshot_hash` SHA-256 + `algorithm` + operator; anulare doar cu motiv |
| `raffle_winners` | câștigători | UNIQUE (`raffle_draw_id`, `user_id`) |
| `reviews` | recenzii moderate | UNIQUE (`user_id`, `producer_id`, `event_id`) |
| `document_templates`, `contracts`, `annexes` | documente | `number` UNIQUE, `data_snapshot` JSON |
| `task_templates`, `tasks` | task engine | UNIQUE (`event_id`, `task_template_id`), deci generarea e idempotentă |
| `notification_templates` | template RO/HU | UNIQUE (`key`, `channel`) |
| `notification_logs` | jurnal comunicări | `dedupe_key` UNIQUE (fără mesaje duble), `status` INDEX |
| `consents` | GDPR | tip, versiune, granted, IP, user-agent, dată |
| `audit_logs` | audit | acțiune, entitate, valori vechi/noi, IP |
| `application_updates`, `update_backups` | Update Manager | `uuid` UNIQUE; log JSON pe pași |
| `settings` | setări CRM (inclusiv URL Autentik Shop) | `key` UNIQUE |

## Date de sistem

Rolurile, permisiunile, template-urile de notificare RO/HU, template-urile de task, regulile de fidelitate inițiale, template-urile de contract/anexă și categoriile sunt inserate de migrația `2026_10_14_002000_seed_system_data`, prin `SystemDataSeeder` (idempotent). Seeder-ul nu suprascrie textele editate din CRM.

Locațiile inițiale (Târgu Mureș, Reghin) cu layout-uri DEMO: `php artisan db:seed --force` (`VenueSeeder`).
Date demo (doar local): `php artisan db:seed --class=DemoSeeder`.
