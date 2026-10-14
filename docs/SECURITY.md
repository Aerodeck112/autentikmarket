# SECURITY

## Autentificare și sesiuni

- Parole hash-uite (bcrypt, `BCRYPT_ROUNDS=12`), politică minimă: 8 caractere, litere și cifre (Super Admin: 10).
- Rate limiting: login 5/min per e-mail+IP și 20/min per IP; formulare 10/min; scanări 120/min; hartă 60/min; upload update 10/min.
- Resetarea parolei nu dezvăluie dacă adresa există. Linkurile expiră în 60 de minute.
- Confirmarea e-mailului este obligatorie pentru tombole (înscriere proprie), recenzii (prin linkuri semnate) și trimiterea profilului de producător spre verificare.
- Sesiuni în DB, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` (HTTPS), `HttpOnly`, `SameSite=Lax`. Sesiunea este regenerată la login.
- Conturile dezactivate sau anonimizate sunt deconectate automat (middleware `EnsureActiveUser`).

## Autorizare (RBAC)

- Roluri și permisiuni în DB (`app/Support/Rbac.php`). Toate rutele CRM/Staff au middleware `can:…`, verificat **server-side**.
- `system.update` este acordat doar Super Admin-ului (sau unui rol creat explicit).
- Doar un Super Admin poate acorda sau retrage rolul `super_admin`. Nimeni nu își poate retrage singur acest rol.
- Izolarea producătorilor: fiecare producător vede doar propriile date, documente, media, rezervări și fișiere (verificat în controllere, acoperit de teste).

## Protecții web

- CSRF pe toate formularele și pe apelurile AJAX (`X-CSRF-TOKEN`).
- XSS: Blade escapează implicit. Template-urile de notificare nu sunt Blade (substituție de text cu valori escapate). Template-urile de contract au valorile escapate, iar scripturile și atributele `on*` sunt eliminate.
- SQL: Eloquent / query builder cu bindings. Căutarea escapează `%` și `_`.
- Headere de securitate: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` (cameră doar pe propriul domeniu), HSTS pe HTTPS.
- CSV injection: celulele care încep cu `= + - @` sunt prefixate cu apostrof.
- Honeypot anti-spam pe formularele publice (contact, newsletter).
- `APP_DEBUG=false` în producție. Paginile de eroare sunt proprii (403, 404, 419, 429, 500, 503), fără stack trace.

## Upload-uri

- Validare de extensie, MIME real (`mimetypes`), dimensiune și dimensiuni minime pentru imagini.
- Fișierele sunt salvate cu nume **UUID**. Extensia vine din MIME-ul real, nu din numele trimis.
- Documentele (PDF/JPG/PNG) ajung pe discul **privat**, servite doar prin `FileController` cu verificare de permisiuni și `Content-Security-Policy: sandbox`.
- Imaginile publice stau în `storage/app/public`, unde `.htaccess` blochează execuția oricărui script (`.php`, `.phtml`, `.phar` etc.).
- `public/.htaccess` blochează fișierele ascunse (`.env`, `.git`).
- `.htaccess`-ul din rădăcină (varianta de rezervă) blochează `app/`, `config/`, `storage/`, `vendor/` etc.

## QR și card

- QR-ul cardului conține doar un URL cu un **token aleator de 40 de caractere**, fără date personale. Tokenul se poate regenera din cont.
- QR-ul producătorului are propriul token aleator. Înregistrarea unei cumpărături cere autentificare și participarea producătorului în ziua respectivă.

## Update Manager

Vezi `UPDATE-PACKAGES.md`. Pe scurt: validare completă înainte de orice scriere, protecție Zip Slip/symlink/zip bomb, listă albă și căi protejate (`.env`), SHA-256 per fișier, semnătură Ed25519 opțională sau obligatorie, fără execuție de cod din pachet, backup obligatoriu, rollback automat al fișierelor, audit pentru `update.installed` / `update.failed` / `update.rollback`.

## GDPR

- Consimțăminte separate (termeni, confidențialitate, marketing, newsletter), cu versiune, dată, IP și user-agent. Acceptarea termenilor **nu** implică marketing.
- Newsletter cu double opt-in. Dezabonarea se face printr-un link cu token.
- Ștergerea contului = anonimizare ireversibilă (statisticile agregate rămân).
- Fonturile sunt self-hosted. Fără cookie-uri de tracking.

## Audit

`audit_logs` înregistrează: aprobare/respingere producător, schimbări de rezervări, contracte/anexe generate, extrageri și anulări de tombolă, premii validate, recompense ridicate, schimbări de roluri și setări, actualizări instalate, eșuate sau anulate. Pentru fiecare: utilizator, entitate, valori vechi/noi, IP, dată.

## Raportarea vulnerabilităților

Contactați administratorul platformei (CRM → Setări → e-mail organizator). Nu publicați detaliile până la remediere.
