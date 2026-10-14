# CPANEL-INSTALL: instalarea inițială pe cPanel

> Instalarea inițială se face **o singură dată**. Toate actualizările ulterioare se fac exclusiv din
> **CRM → Sistem → Actualizări → Încarcă ZIP** (vezi `UPDATE-PACKAGES.md`).

## Cerințe hosting

- cPanel cu **PHP ≥ 8.3** (MultiPHP Manager). Extensii: `pdo_mysql`, `mbstring`, `openssl`, `zip`, `fileinfo`, `gd` (opțional), `intl` (recomandat), `sodium`, `ctype`, `tokenizer`, `xml`, `curl`.
- MySQL 8 sau MariaDB ≥ 10.6.
- Acces la **Cron Jobs**. Recomandat și **Terminal** (SSH) în cPanel.
- `memory_limit` ≥ 256M, `upload_max_filesize` și `post_max_size` ≥ 64M (pentru pachetele de update), `max_execution_time` ≥ 120.

## 1. Upload

1. Primiți pachetul `autentikmarket-install-1.0.0.zip` (generat cu `tools/build-release.sh 1.0.0 --install`; conține deja `vendor/` și `public/build/`).
2. cPanel → **File Manager** → în directorul home (ex. `/home/USER/`), **nu** în `public_html`: Upload → Extract. Rezultă `/home/USER/autentikmarket/`.

## 2. Document root

**Varianta recomandată:** domeniul indică direct directorul `public`.
- cPanel → **Domains** → autentikmarket.ro → *Document Root* = `/home/USER/autentikmarket/public`.

**Varianta de rezervă** (dacă document root-ul nu poate fi schimbat): extrageți aplicația direct în `public_html/`. `.htaccess`-ul din rădăcină redirecționează totul către `public/` și blochează accesul la `app/`, `config/`, `storage/`, `vendor/`, `.env` etc. Verificați după instalare că `https://autentikmarket.ro/.env` și `https://autentikmarket.ro/storage/` răspund cu **403/404**.

## 3. Baza de date

cPanel → **MySQL Databases**:
1. Creați baza `USER_autentik`.
2. Creați utilizatorul `USER_autentik` cu o parolă puternică.
3. Adăugați utilizatorul la bază cu **ALL PRIVILEGES**.

## 4. `.env`

În File Manager copiați `.env.example` → `.env` și completați:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://autentikmarket.ro
DB_CONNECTION=mysql        # sau mariadb
DB_HOST=localhost
DB_DATABASE=USER_autentik
DB_USERNAME=USER_autentik
DB_PASSWORD=********
MAIL_HOST=mail.autentikmarket.ro
MAIL_USERNAME=noreply@autentikmarket.ro
MAIL_PASSWORD=********
```

`.env` nu este suprascris niciodată de Update Manager.

## 5. Composer

Pachetul de instalare include deja `vendor/`, deci **nu** este nevoie să rulați Composer. Dacă instalați din git (fără `vendor/`), din Terminal:

```bash
cd ~/autentikmarket
composer install --no-dev --optimize-autoloader
```

## 6. Cheia aplicației

```bash
cd ~/autentikmarket
php artisan key:generate --force
```

Fără Terminal: generați local cheia cu `php artisan key:generate --show` și puneți-o în `APP_KEY=` din `.env`.

## 7. Migrații și date inițiale

```bash
php artisan migrate --force
php artisan db:seed --force              # locațiile inițiale (Târgu Mureș, Reghin) cu layout DEMO
php artisan app:create-super-admin       # contul SUPER ADMIN (interactiv)
```

Rolurile, permisiunile și template-urile RO/HU sunt inserate automat de migrații.

## 8. Storage

```bash
php artisan storage:link
```

Dacă hostingul nu permite symlink-uri: cPanel → File Manager → creați manual linkul `public/storage` → `../storage/app/public`, sau cereți suportului să activeze `FollowSymLinks`.

## 9. Permisiuni

- Directoare `755`, fișiere `644`.
- `storage/` și `bootstrap/cache/` trebuie să poată fi scrise de utilizatorul PHP (pe cPanel cu suPHP/PHP-FPM, utilizatorul contului: `755` e suficient).
- Pentru Update Manager, utilizatorul PHP trebuie să poată scrie în `app/`, `config/`, `resources/`, `routes/`, `database/`, `lang/`, `public/build/`, `vendor/`. Pe cPanel standard, fișierele aparțin contului, deci condiția este îndeplinită. Pre-flight check-ul verifică oricum înainte de fiecare update.

## 10. Cron

cPanel → **Cron Jobs** → *Common settings: Once Per Minute* (`* * * * *`):

```bash
/usr/local/bin/php /home/USER/autentikmarket/artisan schedule:run >> /dev/null 2>&1
```

Calea PHP corectă (`/usr/local/bin/php` sau `/opt/cpanel/ea-php83/root/usr/bin/php`) apare în MultiPHP Manager. Detalii în `CRON.md`.

## 11. SMTP

- cPanel → **Email Accounts** → creați `noreply@autentikmarket.ro`.
- *Connect Devices* afișează serverul SMTP (de regulă `mail.autentikmarket.ro`, port 465 SSL), care se completează în `.env`.
- CRM → Setări → **Trimite email test**. Rezultatul apare în CRM → Automatizări → Jurnal notificări (queued → sent / failed).
- Recomandat: SPF, DKIM, DMARC active (cPanel → Email Deliverability).

## 12. Cache (producție)

```bash
php artisan optimize        # config + rute + view-uri + evenimente
```

După orice modificare manuală în `.env`: `php artisan optimize:clear && php artisan optimize`.

## 13. SSL

- cPanel → **SSL/TLS Status** → AutoSSL (Let's Encrypt) pentru domeniu.
- Apoi decomentați în `public/.htaccess` regula de redirect HTTPS.
- `.env`: `SESSION_SECURE_COOKIE=true`.

## 14. Test final

- [ ] `https://autentikmarket.ro` redirecționează spre `/ro` (sau `/hu` pentru browsere în maghiară)
- [ ] `/ro` și `/hu` se încarcă, iar switcher-ul RO/HU păstrează pagina
- [ ] Autentificare Super Admin → `/crm`
- [ ] CRM → Sistem: versiunea `1.0.0`, Debug `OFF`, ultimul cron în ultimul minut
- [ ] CRM → Setări: datele organizatorului, URL-ul Autentik Shop, logo
- [ ] Trimite email test → status „Trimis”
- [ ] CRM → Hartă & standuri: editorul se deschide
- [ ] Creați un eveniment de test → harta și checklist-ul sunt generate
- [ ] `/staff` pe telefon: scannerul pornește camera (necesită HTTPS)
- [ ] `/.env`, `/storage/logs/` și `/vendor/` răspund cu 403/404

## După instalare

Actualizările viitoare: **CRM → Sistem → Actualizări → Încarcă ZIP**. Fără File Manager, fără SQL manual, fără `npm`.
