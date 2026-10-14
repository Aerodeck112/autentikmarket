# BACKUP

## 1. Backup automat la fiecare actualizare

Update Manager-ul creează **înainte** de orice modificare:

```
storage/app/private/backups/updates/AAAALLZZ-HHMMSS-vX-to-vY-xxxxxxxx/
  files.zip          fișierele aplicației care vor fi suprascrise / șterse
  database.sql.gz    dump-ul complet al bazei de date (PHP pur, fără mysqldump)
  version.json       versiunea anterioară, versiunea țintă, PHP, dată
```

- Directorul nu este public: are `.htaccess` cu `Require all denied`, iar `storage/` se află în afara `public/`.
- Se păstrează ultimele `UPDATE_KEEP_BACKUPS` (implicit 5). Cele mai vechi se șterg automat.
- Lista fișierelor **noi** introduse de update este salvată pentru rollback.

## 2. Rollback fișiere

- **Automat**: dacă un pas eșuează după instalarea fișierelor (install / migrate / optimize / verify), fișierele anterioare sunt restaurate, iar aplicația revine online.
- **Manual**: CRM → Sistem → Actualizări → actualizarea → „Rollback fișiere”, sau CLI:

```bash
php artisan update:rollback <uuid>
```

**Baza de date NU este restaurată automat.** Siguranța datelor are prioritate: o restaurare automată ar putea șterge date create după backup (vizite, rezervări). Migrațiile sunt incrementale și compatibile cu codul anterior (vezi `MIGRATION.md`).

## 3. Restaurare manuală a bazei de date (doar în caz extrem)

```bash
cd ~/autentikmarket
php artisan down
zcat storage/app/private/backups/updates/<director>/database.sql.gz | mysql -u USER_autentik -p USER_autentik
php artisan up
```

Sau din cPanel → phpMyAdmin → Import (după dezarhivarea fișierului `.gz`).

## 4. Backup periodic (recomandat)

Pe lângă backup-urile de update, configurați backup-ul periodic al contului:
- cPanel → **Backup** / **JetBackup** (dacă hostingul îl oferă): backup zilnic complet (fișiere + DB);
- sau un cron zilnic pentru dump-ul DB:

```bash
15 2 * * * mysqldump -u USER_autentik -p'PAROLA' USER_autentik | gzip > /home/USER/backups/db-$(date +\%F).sql.gz
```

Fișiere importante de inclus: `.env`, `storage/app/private/` (contracte, anexe, documente), `storage/app/public/` (fotografii).
