# Autentik Market — note pentru dezvoltare

Platformă Laravel 13 (PHP ≥ 8.3) pentru târgul lunar Autentik Market. Rulează pe **cPanel** (Apache + PHP + MySQL/MariaDB), fără Node/Redis/Supervisor în producție.

## Reguli
- Texte UI: scrise în română prin `__('…')`; traducerea HU obligatorie în `lang/hu.json` (testul `TranslationCoverageTest` verifică).
- Conținut din DB: coloane `_ro` / `_hu`, afișate cu `$model->t('camp')`.
- Rute publice: `lroute('nume')` (generează `/ro/...` sau `/hu/...`).
- Notificări: DOAR prin `App\Services\Notifications\NotificationService` (limba se alege din destinatar).
- Permisiuni: `can:permisiune` (RBAC propriu, `App\Support\Rbac`).
- Migrații: incrementale, ne-distructive (vezi `docs/MIGRATION.md`). Date de sistem noi → seeder idempotent apelat dintr-o migrație.
- Nu cache-ui modele Eloquent (Laravel 13 restricționează unserialize).
- Versiune: fișierul `VERSION`. Release: `docs/RELEASE-PROCESS.md`.

## Comenzi
- `composer install && npm install && npm run build`
- `php artisan test` (SQLite in-memory) · pe MariaDB: `DB_CONNECTION=mariadb DB_DATABASE=… php vendor/bin/phpunit`
- `vendor/bin/pint`
- `php artisan release:build X.Y.Z` → `releases/dist/autentikmarket-update-X.Y.Z.zip`
