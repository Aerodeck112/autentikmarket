# Autentik Market — autentikmarket.ro

Platforma digitală a **Autentik Market**, târgul lunar de producători locali și artizani (Târgu Mureș / Marosvásárhely). Complet bilingvă **RO / HU**. Proiectată pentru **cPanel** (Apache + PHP 8.3+ + MySQL/MariaDB), fără Node, Redis sau Supervisor în producție.

Ce conține: site public RO/HU · CRM · Card Autentik (QR) · zona producătorului · Staff PWA (check-in) · evenimente și calendar · hărți interactive + editor · rezervări de standuri sigure la concurență · contracte și anexe PDF · fidelizare · tombole auditabile · recenzii · task-uri · notificări RO/HU · statistici pe limbi · blog · **Update Manager prin ZIP**.

Autentik Shop rămâne o aplicație externă: aici apare doar ca link configurabil (CRM → Setări → Integrări).

## Documentație

| Fișier | Conținut |
|---|---|
| [docs/RESEARCH.md](docs/RESEARCH.md) | research despre proiect, identitate, surse |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | arhitectura, modulele, RO/HU, hărți, digitizarea schițelor |
| [docs/DATABASE.md](docs/DATABASE.md) | schema și constrângerile |
| [docs/DECISIONS.md](docs/DECISIONS.md) | deciziile tehnice |
| [docs/CPANEL-INSTALL.md](docs/CPANEL-INSTALL.md) | instalarea inițială pe cPanel |
| [docs/CRON.md](docs/CRON.md) | scheduler și coadă |
| [docs/BACKUP.md](docs/BACKUP.md) | backup și restaurare |
| [docs/UPDATE-PACKAGES.md](docs/UPDATE-PACKAGES.md) | formatul pachetelor ZIP |
| [docs/RELEASE-PROCESS.md](docs/RELEASE-PROCESS.md) | cum se generează o versiune nouă |
| [docs/MIGRATION.md](docs/MIGRATION.md) | reguli pentru migrații |
| [docs/SECURITY.md](docs/SECURITY.md) | securitate și GDPR |

## Dezvoltare locală

```bash
composer install && npm install && npm run build
cp .env.example .env    # DB_CONNECTION=sqlite pentru local
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # date demo (admin@example.test / Parola123)
php artisan serve
php artisan test
```

Versiunea curentă: vezi fișierul [`VERSION`](VERSION).
