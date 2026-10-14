# UPDATE-PACKAGES: formatul pachetelor de actualizare

## Numele fișierului

```
autentikmarket-update-X.Y.Z.zip        (Semantic Versioning)
```

## Structura arhivei

```
autentikmarket-update-1.5.0.zip
├── update.json              manifestul (obligatoriu)
├── update.sig               semnătură Ed25519 a update.json (opțional / obligatoriu din config)
├── release-notes.md         noutăți RO (opțional)
├── release-notes.hu.md      noutăți HU (opțional)
└── files/                   fișierele aplicației, cu căi relative la rădăcina aplicației
    ├── VERSION              obligatoriu, conține exact versiunea din manifest
    ├── app/…
    ├── config/…
    ├── database/migrations/…  migrațiile noi (rulate cu `migrate --force`)
    ├── lang/…
    ├── public/build/…       asset-urile COMPILATE (CSS/JS + manifest Vite)
    ├── resources/views/…
    ├── routes/…
    └── vendor/…             doar când dependențele Composer s-au schimbat
```

Orice altă intrare în rădăcina arhivei duce la respingerea pachetului.

## Manifestul `update.json`

```json
{
  "format": 1,
  "application": "autentikmarket",
  "version": "1.5.0",
  "minimum_version": "1.4.0",
  "release_date": "2026-11-20",
  "requires": { "php": ">=8.3", "extensions": ["pdo_mysql", "zip", "mbstring", "openssl", "fileinfo", "sodium"] },
  "database_migration_required": true,
  "includes_vendor": false,
  "description": { "ro": "Raport participanți nou", "hu": "Új résztvevői jelentés" },
  "files": {
    "VERSION": "<sha256>",
    "app/Http/Controllers/Crm/ReportController.php": "<sha256>",
    "public/build/manifest.json": "<sha256>"
  },
  "delete": ["app/Services/Old/LegacyThing.php"],
  "checksum": "<sha256 al listei canonice>"
}
```

| Câmp | Semnificație |
|---|---|
| `application` | trebuie să fie `autentikmarket` |
| `version` | versiunea nouă (X.Y.Z), strict mai mare decât cea instalată |
| `minimum_version` | versiunea minimă instalată de la care se poate aplica pachetul |
| `requires.php` / `extensions` | verificate înainte de instalare |
| `database_migration_required` | informativ (migrațiile rulează oricum, idempotent) |
| `includes_vendor` | pachetul conține `vendor/` (dependențe Composer schimbate) |
| `files` | **fiecare** fișier din `files/` cu hash-ul SHA-256; lista trebuie să coincidă exact cu arhiva |
| `delete` | fișiere de șters (doar din căile permise) |
| `checksum` | `sha256(join("\n", sort("cale:hash") + sort("delete:cale")))` |

## Validări (în ordine)

1. Extensia `.zip`, tipul MIME, semnătura binară `PK\x03\x04`, dimensiunea maximă.
2. Pentru fiecare intrare: **protecție Zip Slip**. Sunt respinse căile absolute, `..`, `.`, segmentele goale, backslash-urile, octeții NUL, caracterele de control și `:`.
3. Sunt respinse symlink-urile, intrările criptate și arhivele cu raport de compresie suspect (zip bomb).
4. Structura: doar `update.json`, `update.sig`, `release-notes*.md` în rădăcină. Restul trebuie să fie sub `files/`.
5. Fiecare cale din `files/` trebuie să fie în **lista albă** și să nu atingă o **cale protejată**.
6. Manifest valid, `application`, versiune (Semver, mai nouă), compatibilitate (`minimum_version`), PHP și extensii.
7. Lista de fișiere din arhivă trebuie să fie identică cu manifestul. `files/VERSION` trebuie să fie egal cu `version`.
8. Checksum-ul global și **SHA-256 pentru fiecare fișier**.
9. Semnătura Ed25519 (dacă există cheie publică). Cu `UPDATE_REQUIRE_SIGNATURE=true`, pachetele nesemnate sunt respinse.
10. Scanarea migrațiilor pentru operații distructive (`drop`, `dropColumn`, `truncate`, `delete`, `rename`). Dacă apar, instalarea cere **confirmarea explicită** a administratorului.

### Lista albă (`config/update.php → allowed_paths`)

`app/`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/`, `database/{migrations,seeders,factories}/`, `lang/`, `public/build/`, `public/images/`, `public/icons/`, `public/index.php`, `public/.htaccess`, `public/favicon.*`, `public/manifest.webmanifest`, `public/sw.js`, `resources/`, `routes/`, `vendor/`, `artisan`, `composer.json`, `composer.lock`, `VERSION`, `docs/`.

În `public/` este permis un singur fișier PHP: `public/index.php`.

### Căi protejate (niciodată scrise sau șterse)

`.env`, `.env.*`, `storage/` (upload-uri, documente private, log-uri, backup-uri), `public/storage`, `public/uploads/`, `bootstrap/cache/`, `.htaccess` din rădăcină, `.git/`, `database/database.sqlite`.

La scriere se verifică în plus `realpath`-ul directorului părinte, ca un symlink din interiorul aplicației să nu poată duce în afara ei.

## Pașii instalării

| Pas | Ce face | Status |
|---|---|---|
| upload + validare | copiere în `storage/app/private/updates/packages/{uuid}.zip`, validare completă | `uploaded` → `validated` / `rejected` |
| `preflight` | PHP, extensii, spațiu pe disc, drepturi de scriere, DB, storage, director backup, versiune, confirmare migrații distructive | — |
| `backup` | `files.zip` (fișierele afectate) + `database.sql.gz` + `version.json` | `backup_created` |
| `maintenance` | `artisan down` (pagină 503 RO/HU) | `installing` |
| `install` | scriere atomică (tmp + rename) a fiecărui fișier, verificare hash după scriere, ștergerile din `delete`; `VERSION` se scrie ultimul | — |
| `migrate` | `artisan migrate --force` (doar incremental) | — |
| `optimize` | `optimize:clear` + `optimize` (config, rute, view-uri, evenimente) + `opcache_reset` | — |
| `verify` | `VERSION` = versiunea nouă, hash-ul tuturor fișierelor, conexiune DB, zero migrații rămase | — |
| `finalize` | `artisan up`, audit `update.installed` | `completed` |

Fiecare pas este un **request HTTP separat**, deci un proces PHP nou. Pașii de după copierea fișierelor rulează astfel cu codul nou, iar limita `max_execution_time` nu este atinsă. Rutele pașilor rămân accesibile în maintenance mode (excepție configurată), dar sunt protejate de autentificare și de permisiunea `system.update`.

**La eșec după `install`:** fișierele sunt restaurate automat din backup (`rolled_back`), iar aplicația revine online. Baza de date **nu** este restaurată automat (vezi `BACKUP.md`).

## Ce NU face niciodată Update Manager-ul

- nu execută scripturi din pachet (fără hook-uri, `eval`, `exec`, `shell_exec`);
- nu suprascrie `.env`, upload-urile, storage-ul privat, log-urile sau backup-urile;
- nu importă dump-uri SQL care să înlocuiască baza existentă;
- nu rulează `migrate:fresh` / `db:wipe`;
- nu rulează `npm` sau `composer` pe server.

## Instalare din linia de comandă (alternativă)

```bash
php artisan update:install /cale/autentikmarket-update-1.5.0.zip [--yes] [--confirm-destructive]
php artisan update:rollback <uuid>
```

## Istoric

CRM → Sistem → Actualizări → Istoric: versiune, versiunea anterioară, dată, administrator, status, durată, backup, mesaj. Fiecare instalare are un jurnal detaliat pe pași.
