# DECISIONS: deciziile tehnice

Format: **D-xx: Decizie**, apoi context, alegere și motiv. Deciziile minore au fost luate după regula din brief: *cea mai robustă și compatibilă cu cPanel*.

---

### D-01: Laravel 13 + PHP ≥ 8.3
- Verificat la 14.10.2026: Laravel 13.x este versiunea stabilă (13.36). Cere PHP ^8.3. Laravel 12 a intrat în perioada „security fixes only”.
- PHP 8.3 / 8.4 sunt disponibile prin **MultiPHP Manager** pe majoritatea hostingurilor cPanel actuale.
- `composer.json` conține `config.platform.php = 8.3.0`, deci dependențele rezolvate rămân instalabile pe PHP 8.3 chiar dacă release-ul este construit pe o mașină cu PHP mai nou.
  - Exemplu: `openspout/openspout` 5.12 cere PHP 8.4, așa că s-a ales automat 5.3.

### D-02: Blade + Alpine.js + Tailwind CSS 4 (fără Livewire la v1.0)
- Brief-ul preferă „Livewire/Alpine”. Am evaluat ambele variante și am ales **Blade + Alpine.js** pentru v1.0:
  - componentele cu adevărat interactive (editorul de hartă, harta producătorului, scannerul QR) sunt module JS dedicate (SVG + pointer events), unde Livewire nu aduce avantaje;
  - CRM-ul folosește formulare server-side și filtre prin query string, care pot fi salvate ca bookmark și reutilizate identic pentru export CSV/XLSX;
  - mai puține piese mobile la actualizarea prin ZIP: fără asset-uri publicate de pachete și fără endpoint-uri `/livewire/*` de versionat;
  - zero dependență de Node la runtime: Vite compilează asset-urile o singură dată, iar acestea sunt incluse în pachet.
- Livewire 4 poate fi adăugat ulterior fără refactorizare, dacă apare un caz justificat.

### D-03: MySQL/MariaDB în producție, SQLite pentru teste
- Migrațiile folosesc doar tipuri portabile: `decimal`, `json`, `string`, `text`, `timestamp`.
- Constrângerile critice (unicitate check-in, unicitate stand activ) sunt **constrângeri DB reale**, valabile pe ambele motoare.
- Testat și pe MariaDB (vezi `tests/` și secțiunea „Teste” din `ARCHITECTURE.md`).

### D-04: RBAC propriu (tabele roles / permissions)
- Nu folosim `spatie/laravel-permission`. Implementarea proprie are ~150 de linii, zero dependențe și migrații controlate de noi, ceea ce contează pentru siguranța actualizărilor prin ZIP.
- Verificarea se face server-side prin `Gate::before()`: orice `can('permisiune')` din rute, controllere sau view-uri interoghează tabelele RBAC.
- `super_admin` trece implicit toate verificările. Excepție: `system.update` este acordat explicit și rolului super_admin, plus oricui primește permisiunea separat.

### D-05: Rezervarea concurentă a standurilor
Trei niveluri de protecție, fără încredere în frontend:
1. **tranzacție DB** + `SELECT … FOR UPDATE` pe rândul `event_slots`;
2. coloana `stand_reservations.active_slot_id` cu **UNIQUE INDEX**. Este egală cu `event_slot_id` cât timp rezervarea este activă (`hold`, `requested`, `approved`) și `NULL` în rest. MySQL permite oricâte `NULL` într-un index unique, dar un singur rând activ per stand;
3. coloana `active_producer_key` (UNIQUE): un producător are cel mult o rezervare activă per eveniment (configurabil prin `max_stands_per_producer` în viitor).
- O coliziune pe constraint este tratată ca „stand indisponibil” (`QueryException` 23000), nu ca eroare 500.

### D-06: Hold temporar fără WebSocket
- `hold_expires_at` (implicit 15 minute, configurabil din Setări).
- Expirarea se face pe 3 căi: (a) comanda `reservations:expire-holds`, rulată în fiecare minut de scheduler; (b) verificare „lazy” la fiecare request pe harta evenimentului; (c) verificare în tranzacția de rezervare: un hold expirat nu blochează un alt producător.
- Harta producătorului face polling la 20 s (JSON ușor, cache 5 s), deci nu este nevoie de WebSocket.

### D-07: Coada de joburi = `database`, procesată de scheduler
- Fără Supervisor. `routes/console.php` programează `queue:work --stop-when-empty --max-time=50` în fiecare minut, cu `withoutOverlapping()`.
- Pe cPanel există **un singur cron**: `* * * * * php artisan schedule:run`. Detalii în `CRON.md`.

### D-08: URL-uri bilingve cu slug-uri traduse
- Site public: `/ro/...` și `/hu/...` cu slug-uri traduse (`/ro/producatori` ↔ `/hu/termelok`, `/ro/targuri` ↔ `/hu/vasarok`).
- Rutele sunt generate dintr-o singură definiție (`routes/web.php` + `lang/{ro,hu}/routes.php`). Numele rutelor sunt `ro.producers.index` / `hu.producers.index`, iar helper-ul `lroute('producers.index')` alege automat limba curentă.
- `/` face redirect către `/ro` sau `/hu` după cookie, apoi după `Accept-Language`.
- Zonele autentificate (`/account`, `/producer`, `/staff`, `/crm`) **nu** au prefix de limbă: nu sunt indexabile, iar limba vine din `preferred_language` (cu switch în interfață).
- SEO: `hreflang` RO/HU + `x-default`, `canonical`, `sitemap.xml` cu alternate-uri, metadata separată RO/HU.

### D-09: Traduceri UI: chei în română + `lang/hu.json`
- Textele din view-uri sunt scrise în română și trecute prin `__()`. Româna este limba-sursă și fallback, iar maghiara stă în `lang/hu.json`.
- Un test automat verifică faptul că **fiecare** cheie `__('…')` din `resources/views` și `app/` are traducere în `hu.json`. Nu poate ajunge în producție un text netradus.
- Conținutul dinamic (evenimente, categorii, recompense, template-uri) folosește coloane `_ro` / `_hu`. Accesorul `$model->t('title')` alege limba curentă, cu fallback pe română.
- Recomandare: o revizie a textelor HU de către un vorbitor nativ înainte de lansare.

### D-10: Motorul de notificări centralizat
- Un singur punct de intrare: `NotificationService::send($key, $recipient, $data)`. Niciun controller nu trimite mail direct.
- Limba este dedusă din destinatar (`preferredLanguage()`): `User` → `preferred_language`, `Producer` → `preferred_language` al producătorului.
- Fiecare mesaj este logat în `notification_logs` (queued → processing → sent / failed). Un `dedupe_key` unic previne trimiterea dublă (de exemplu „mulțumire eveniment X utilizator Y”).
- Placeholder-ele `{{name}}` sunt înlocuite cu valori escapate HTML. Template-urile nu pot executa cod (nu sunt compilate ca Blade).

### D-11: PDF cu dompdf
- `barryvdh/laravel-dompdf` (PHP pur, fără Chrome/Puppeteer, funcționează pe orice cPanel).
- Template-urile de contract/anexă sunt HTML editabil în CRM, cu placeholder-e `{{producer_name}}` etc.
- PDF-urile sunt salvate pe discul `private` (`storage/app/private/contracts|annexes`) și servite doar printr-un controller cu verificare de permisiuni.

### D-12: QR cu chillerlan/php-qrcode (SVG)
- QR-ul este generat ca SVG server-side, fără GD/Imagick.
- Cardul conține **URL-ul** `https://…/staff/scan/{card_token}`:
  - token aleator de 40 de caractere, fără date personale, regenerabil din cont;
  - scanat cu camera nativă de un membru staff autentificat, deschide direct fișa de check-in;
  - scanat de altcineva, cere autentificare și nu dezvăluie nimic.
- Scanner în browser: `jsQR` + `getUserMedia`, în modul PWA staff, cu fallback pe introducere manuală a codului de membru (`AM-XXXXXX`).

### D-13: Export CSV + XLSX cu OpenSpout
- Streaming, memorie mică, potrivit pentru limitele de memorie de pe cPanel. Fără PhpSpreadsheet.

### D-14: Versiunea aplicației = fișierul `VERSION`
- Sursă unică: `VERSION` în rădăcina aplicației, citit prin `App\Support\AppVersion`.
- Pachetul de update trebuie să conțină un `files/VERSION` identic cu `manifest.version`, altfel este respins. Istoricul instalărilor se află în `application_updates`.

### D-15: Update Manager în pași HTTP separați
- Instalarea rulează ca o secvență de request-uri scurte (`preflight → backup → install → migrate → optimize → verify → finalize`), declanșate din interfață.
- Fiecare pas pornește un **proces PHP nou**, așa că pașii de după copierea fișierelor rulează cu codul nou, nu cu clasele vechi încărcate în memorie.
- Evită `max_execution_time` pe hostinguri restrictive.
- Există și varianta CLI: `php artisan update:install pachet.zip`.
- Detalii complete în `UPDATE-PACKAGES.md`.

### D-16: Fără execuție de cod din pachet
- Pachetul conține **doar fișiere** copiate într-o listă albă de directoare și migrații Laravel standard.
- Nu există hook-uri `pre_install.php`, `eval()`, `exec()` sau `shell_exec()` în Update Manager.
- Migrațiile sunt cod PHP, dar fac parte din aplicație (au trecut prin același proces de release și, opțional, prin semnătură Ed25519). Sunt rulate exclusiv prin `migrate --force`.
- Migrațiile care conțin operații destructive (`drop`, `dropColumn`, `truncate`, `delete`) sunt detectate și cer o confirmare explicită suplimentară.

### D-17: Semnătură Ed25519 (opțională, recomandată)
- Extensia `sodium` este inclusă în PHP ≥ 7.2.
- `php artisan update:keygen` (pe mașina de dezvoltare) generează o pereche de chei. Cheia publică ajunge în `config/update.php`. Cheia secretă **nu** se pune niciodată în repo.
- Dacă `UPDATE_REQUIRE_SIGNATURE=true`, pachetele nesemnate sunt respinse.

### D-18: Backup DB în PHP pur
- `mysqldump` sau `exec()` sunt adesea indisponibile pe cPanel, așa că backup-ul DB este generat în PHP, în streaming: `SHOW CREATE TABLE` + `INSERT` în loturi, comprimat gzip.
- Destinație: `storage/app/private/backups/updates/{data}-{versiune}/`, inaccesibilă din web.

### D-19: Fișiere commit-uite: `public/build`
- Asset-urile compilate (`public/build`) sunt versionate în git. Instalarea inițială pe cPanel nu necesită Node, iar pachetele ZIP le includ oricum.

### D-20: Fonturi self-hosted
- `@fontsource-variable/fraunces` și `@fontsource-variable/inter` sunt împachetate de Vite. Fără request-uri externe (GDPR) și fără dependență de CDN.

### D-21: Ștergerea conturilor = anonimizare
- Datele statistice (check-in, interacțiuni) se păstrează, dar datele personale sunt anonimizate (`first_name = "Utilizator"`, `email = deleted-{id}@invalid`).
- Consimțămintele și auditul rămân pentru conformitate.

### D-22: AI opțional pentru blog
- Draft-ul post-eveniment este generat **din template** (RO + HU) cu statisticile reale ale evenimentului.
- Există interfața `BlogDraftGenerator` cu implementarea `TemplateBlogDraftGenerator`. Un generator AI poate fi adăugat ulterior prin `Setări → Integrări`.
- Niciun draft nu este publicat automat: `draft → review → published`, doar manual.
