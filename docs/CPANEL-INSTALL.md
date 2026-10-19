# CPANEL-INSTALL: instalarea inițială pe cPanel

> Instalarea inițială se face **o singură dată**. Toate actualizările ulterioare se fac exclusiv din
> **CRM → Sistem → Actualizări → Încarcă ZIP** (vezi `UPDATE-PACKAGES.md`).

## Cerințe hosting

- cPanel cu **PHP ≥ 8.3** (MultiPHP Manager). Extensii: `pdo_mysql`, `mbstring`, `openssl`, `zip`, `fileinfo`, `gd` (opțional), `intl` (recomandat), `sodium`, `ctype`, `tokenizer`, `xml`, `curl`.
- MySQL 8 sau MariaDB ≥ 10.6.
- Acces la **Cron Jobs**. Recomandat și **Terminal** (SSH) în cPanel.
- `memory_limit` ≥ 256M, `upload_max_filesize` și `post_max_size` ≥ 64M (pentru pachetele de update), `max_execution_time` ≥ 120.

## 1. Upload

1. Primiți pachetul `autentikmarket-install-X.Y.Z.zip` (ultima versiune, generat cu `tools/build-release.sh X.Y.Z --install`; conține deja `vendor/` și `public/build/`). Instalați direct ultima versiune: nu e nevoie să instalați 1.0.0 și apoi update-urile.
2. Varianta A (document root modificabil): cPanel → **File Manager** → în directorul home (ex. `/home/USER/`), **nu** în `public_html`: Upload → Extract. Rezultă `/home/USER/autentikmarket/`.
3. Varianta B (document root fix pe `public_html`): urmați pașii din §2 B.

## 2. Document root

### Varianta A: domeniul indică directorul `public`

Se folosește când cPanel permite schimbarea document root-ului (de regulă la addon domains și subdomenii):
- cPanel → **Domains** → domeniul → *Document Root* = `/home/USER/autentikmarket/public`.

### Varianta B: document root fix (`public_html`)

Domeniul principal al contului are de obicei document root-ul fixat pe `public_html`. Aplicația se extrage **direct în `public_html`**:

1. File Manager → `public_html` → ștergeți fișierele implicite ale hostingului (ex. `index.html`, `default.html`, `cgi-bin` gol), dacă există.
2. Upload `autentikmarket-install-X.Y.Z.zip` în `public_html` → Extract. Rezultă folderul `public_html/autentikmarket/`.
3. Intrați în `public_html/autentikmarket/` → **Select All** (bifați și fișierele ascunse: *Settings → Show Hidden Files*, pentru `.htaccess` și `.env.example`) → **Move** → destinația `/public_html`. Apoi ștergeți folderul gol `autentikmarket` și arhiva ZIP.
4. În `public_html` trebuie să existe acum direct `app/`, `public/`, `vendor/`, `artisan`, `.htaccess` etc.

În pașii următori, „directorul aplicației” este `/home/USER/public_html` (în loc de `/home/USER/autentikmarket`), inclusiv în comanda cron.

`.htaccess`-ul din rădăcină trimite totul către `public/`. Fișierele încărcate rămân accesibile la `/storage/…`. Codul, `.env`, logurile, `vendor/` etc. răspund cu 403/404. Adresele cu `/public/…` sau `/index.php` redirecționează la adresa curată. Comportamentul este verificat pe Apache, cu un update instalat din CRM.

După instalare verificați că `https://autentikmarket.ro/.env`, `https://autentikmarket.ro/vendor/autoload.php` și `https://autentikmarket.ro/storage/logs/laravel.log` răspund cu **403/404**.

> `.htaccess`-ul din rădăcină este o cale protejată: **Update Manager nu îl modifică niciodată** (cPanel poate adăuga în el setări proprii, ex. versiunea PHP). Dacă vreo versiune viitoare va avea nevoie de o modificare acolo, notele de release o vor descrie ca pas manual.

## 3. Baza de date

cPanel → **MySQL Databases**:
1. Creați baza `USER_autentik`.
2. Creați utilizatorul `USER_autentik` cu o parolă puternică.
3. Adăugați utilizatorul la bază cu **ALL PRIVILEGES**.

## 4. Instalatorul web (recomandat, fără Terminal)

Deschideți site-ul în browser (`https://autentikmarket.ro`). Cât timp nu există `.env`, el redirecționează automat la **`/install.php`**. Instalatorul:

1. verifică serverul (versiunea PHP, extensiile, permisiunile, limitele de upload);
2. cere **codul de instalare**. Îl găsiți în File Manager, în fișierul `storage/app/private/install-token.txt` (click dreapta → *View*). Codul dovedește că instalarea o face cineva cu acces la găzduire și nu poate fi citit de pe web;
3. cere adresa site-ului, datele bazei de date (testează conexiunea), contul super admin și, opțional, SMTP;
4. scrie `.env` (cu `APP_KEY` generat) și rulează `migrate`, `db:seed` (roluri, șabloane RO/HU, locațiile Târgu Mureș și Reghin cu hărțile oficiale), `storage:link`, crearea contului super admin și `optimize`;
5. afișează comanda exactă pentru **cron** și se **blochează definitiv** (`storage/app/installed.lock`). Codul de instalare este șters.

Dacă un pas eșuează (ex. parolă greșită la baza de date), instalarea poate fi reluată cu același cod. Dacă `storage:link` nu e permis de găzduire, imaginile încărcate sunt servite de aplicație la aceeași adresă `/storage/…`.

**Fără Terminal, după instalare:** modificările făcute în `.env` din File Manager (ex. SMTP, `APP_URL` după SSL) se aplică din **CRM → Sistem → Reîncarcă configurația**.

## 5. Instalare din Terminal (alternativă)

Pachetul include deja `vendor/`, deci nu e nevoie de Composer. Dacă instalați din git (fără `vendor/`), rulați întâi `composer install --no-dev --optimize-autoloader`.

Copiați `.env.example` → `.env` și completați cel puțin:

```dotenv
APP_URL=https://autentikmarket.ro
DB_DATABASE=USER_autentik
DB_USERNAME=USER_autentik
DB_PASSWORD=********
MAIL_HOST=mail.autentikmarket.ro
MAIL_USERNAME=noreply@autentikmarket.ro
MAIL_PASSWORD=********
```

Apoi:

```bash
cd ~/autentikmarket                     # sau ~/public_html (varianta B)
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force              # roluri, șabloane, locațiile Târgu Mureș și Reghin cu hărțile oficiale
php artisan storage:link
php artisan app:create-super-admin       # interactiv; sau --email=… --first-name=… --last-name=… --password=… -n
php artisan optimize
```

`.env` nu este suprascris niciodată de Update Manager.

## 6. Permisiuni

- Directoare `755`, fișiere `644`.
- `storage/` și `bootstrap/cache/` trebuie să poată fi scrise de utilizatorul PHP (pe cPanel cu suPHP/PHP-FPM, utilizatorul contului: `755` e suficient).
- Pentru Update Manager, utilizatorul PHP trebuie să poată scrie în `app/`, `config/`, `resources/`, `routes/`, `database/`, `lang/`, `public/build/`, `vendor/`. Pe cPanel standard, fișierele aparțin contului, deci condiția este îndeplinită. Pre-flight check-ul verifică oricum înainte de fiecare update.

## 7. Cron

cPanel → **Cron Jobs** → *Common settings: Once Per Minute* (`* * * * *`):

```bash
/usr/local/bin/php /home/USER/autentikmarket/artisan schedule:run >> /dev/null 2>&1
```

Calea PHP corectă (`/usr/local/bin/php` sau `/opt/cpanel/ea-php83/root/usr/bin/php`) apare în MultiPHP Manager. Detalii în `CRON.md`.

## 8. SMTP

- cPanel → **Email Accounts** → creați `noreply@autentikmarket.ro`.
- *Connect Devices* afișează serverul SMTP (de regulă `mail.autentikmarket.ro`, port 465 SSL).
- Datele se completează în **CRM → Setări → E-mail (SMTP)** (din versiunea 1.2.0; au prioritate față de `MAIL_*` din `.env`). Parola se păstrează criptată.
- **Salvează și trimite test** trimite imediat și afișează eroarea serverului, explicată (parolă, certificat, port, expeditor).
- Dacă certificatul serverului de e-mail e emis pentru alt nume (des întâlnit pe hosting partajat), folosiți ca server numele din *Connect Devices* sau debifați verificarea certificatului.
- Recomandat: SPF, DKIM, DMARC active (cPanel → Email Deliverability).

## 9. Cache (producție)

Instalatorul web și fiecare update construiesc automat cache-urile (config, rute, view-uri, evenimente).

După orice modificare manuală în `.env`: **CRM → Sistem → Reîncarcă configurația** (sau, din Terminal, `php artisan optimize:clear && php artisan optimize`).

## 10. SSL

> Ideal, activați SSL **înainte** de instalare și deschideți instalatorul cu `https://`: instalatorul pune atunci `SESSION_SECURE_COOKIE=true`. Instalat pe `http://`, pune `false`, ca autentificarea să funcționeze până la activarea SSL.

- cPanel → **SSL/TLS Status** → AutoSSL (Let's Encrypt) pentru domeniu.
- `.env` (File Manager): `APP_URL=https://…` și `SESSION_SECURE_COOKIE=true`, apoi CRM → Sistem → **Reîncarcă configurația**.
- Opțional: decomentați în `public/.htaccess` regula de redirect HTTPS (cPanel are și opțiunea *Force HTTPS Redirect* la Domains).

## 11. Test final

- [ ] `https://autentikmarket.ro` redirecționează spre `/ro` (sau `/hu` pentru browsere în maghiară)
- [ ] `/ro` și `/hu` se încarcă, iar switcher-ul RO/HU păstrează pagina
- [ ] Autentificare Super Admin → `/crm`
- [ ] CRM → Sistem: versiunea instalată (ex. `1.1.0`), Debug `OFF`, ultimul cron în ultimul minut
- [ ] CRM → Setări: datele organizatorului, URL-ul Autentik Shop, logo
- [ ] Trimite email test → status „Trimis”
- [ ] CRM → Hartă & standuri: hărțile Târgu Mureș (20 de standuri) și Reghin (13 standuri) sunt MASTER
- [ ] Creați un eveniment de test → harta și checklist-ul sunt generate
- [ ] `/staff` pe telefon: scannerul pornește camera (necesită HTTPS)
- [ ] `/.env`, `/storage/logs/` și `/vendor/` răspund cu 403/404

## După instalare

Actualizările viitoare: **CRM → Sistem → Actualizări → Încarcă ZIP**. Fără File Manager, fără SQL manual, fără `npm`.
