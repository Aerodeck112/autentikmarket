# ARCHITECTURE: arhitectura aplicației

## 1. Principiu

```
PRODUCĂTOR  ↔  AUTENTIK MARKET  ↔  VIZITATOR
```

Toate modulele alimentează această relație. Platforma știe:
- cine sunt producătorii și vizitatorii și ce limbă vorbesc;
- la ce târg a participat fiecare și ce stand a ocupat producătorul;
- de câte ori a revenit clientul, ce recompense are, dacă a câștigat la tombolă;
- ce producător a evaluat și ce comunicări a primit.

## 2. Mediul de rulare (cPanel)

| Componentă | Folosit | NU este necesar |
|---|---|---|
| Web server | Apache + `.htaccess` (mod_rewrite) | Nginx, Docker |
| Runtime | PHP ≥ 8.3 (MultiPHP) | Node.js permanent, PM2 |
| Bază de date | MySQL 8 / MariaDB ≥ 10.6 | Elasticsearch, Meilisearch |
| Coadă | `database` + scheduler | Redis, Supervisor |
| Cache / sesiuni | `database` | Redis, Memcached |
| Automatizări | **un singur** cron: `schedule:run` | WebSocket server |
| E-mail | SMTP (cont cPanel sau extern) | — |
| Frontend | asset-uri compilate (Vite), incluse în pachet | `npm` pe server |

## 3. Straturi

```
routes/web.php ─▶ Http/Controllers/{Site,Auth,Account,Producer,Staff,Crm}
                     │  (validare, autorizare can:…, răspuns Blade/JSON)
                     ▼
                Services/ (logica de business, tranzacții, reguli)
                     │
                     ▼
                Models/ (Eloquent) ─▶ MySQL/MariaDB
```

### Module (app/Services)

| Modul | Responsabilitate |
|---|---|
| `Reservations/StandReservationService` | hold → cerere → aprobare / respingere / anulare / expirare; sigur la concurență |
| `Events/EventLifecycleService` | creare eveniment (snapshot hartă + checklist), tranziții de status, auto-avans |
| `Events/LayoutSnapshotService` | versionarea hărții: copie a layout-ului master pentru fiecare eveniment |
| `Events/PostEventWorkflow` | mulțumiri RO/HU, cereri de recenzie, task-uri post-eveniment, draft blog |
| `Checkin/CheckinService` | check-in QR/manual, o vizită per eveniment |
| `Checkin/InteractionService` | relația client → producător → eveniment („Am cumpărat de aici”) |
| `Loyalty/LoyaltyService` | praguri configurabile, recompense, ridicare atomică |
| `Raffles/RaffleService` | înscriere, eligibilitate, extragere auditabilă (CSPRNG), anulare, validare premiu |
| `Producers/ProducerWorkflow` | NOU → INCOMPLET → DOCUMENTE → ÎN VERIFICARE → APROBAT/RESPINS |
| `Documents/DocumentService` | contracte și anexe PDF din template-uri HTML cu placeholder-e |
| `Notifications/NotificationService` | **motorul central**: template RO/HU, limba destinatarului, log, coadă, dedupe |
| `Tasks/TaskGenerator` | checklist-uri din template-uri (zile relative la data târgului) |
| `Stats/StatisticsService` | statistici per eveniment și globale, raportare pe limbi |
| `Search/GlobalSearch` | căutare CRM (MySQL LIKE) |
| `Export/TableExporter` | CSV/XLSX în streaming (OpenSpout) |
| `Blog/*` | generator de draft (template; interfață pregătită pentru AI) |
| `Updates/*` | **Update Manager** (vezi `UPDATE-PACKAGES.md`) |

## 4. Zone și roluri

| Zonă | URL | Rol / permisiune |
|---|---|---|
| Site public | `/ro/...`, `/hu/...` | oricine |
| Cont vizitator (Card Autentik) | `/account` | autentificat |
| Zona producător | `/producer` | utilizator cu profil de producător |
| Staff PWA (scanner) | `/staff` | `checkin.perform` |
| CRM | `/crm` | `crm.access` + permisiuni granulare |
| Actualizări | `/crm/system/updates` | `system.update` (Super Admin implicit) |

Roluri: `visitor`, `producer`, `staff`, `organizer`, `admin`, `super_admin`. Matricea permisiunilor se află în `app/Support/Rbac.php`. Verificarea se face server-side prin `Gate::before`.

## 5. RO/HU

- **URL**: `/ro/producatori` ↔ `/hu/termelok` (slug-uri în `lang/{ro,hu}/routes.php`). Switcher-ul păstrează pagina curentă.
- **UI**: texte în română prin `__()`, traduse în `lang/hu.json` (acoperire 100%, verificată de test).
- **Conținut**: coloane `*_ro` / `*_hu`, citite cu `$model->t('title')`.
- **Persoane**: `users.preferred_language` și `producers.preferred_language` (`ro` | `hu`), obligatorii la înregistrare, preselectate după versiunea site-ului, modificabile de admin.
- **Comunicări**: `NotificationService` alege `subject_{lang}` / `body_{lang}` după destinatar. Mesajul se trimite **într-o singură limbă**.
- **Statistici**: RO/HU per eveniment, total, în dashboard și rapoarte.

## 6. Rezervarea standurilor — flux

```
producător aprobat ─▶ 1. confirmă datele ─▶ 4. hartă (polling 20 s)
   ─▶ 5. „ALEGE ACEST SPAȚIU” = HOLD (15 min, tranzacție + FOR UPDATE + UNIQUE)
   ─▶ 3+6. necesități + solicitare (REQUESTED)
   ─▶ 7. admin APROBĂ (RESERVED) ─▶ anexă PDF automată ─▶ notificare în limba producătorului
```

Hold-urile expirate sunt eliberate de scheduler (în fiecare minut), lazy la fiecare request pe hartă și în tranzacția de rezervare.

## 7. Hărți

- Coordonatele sunt în **metri** (`x`, `y`, `width`, `height`, `rotation` în jurul centrului). SVG-ul folosește direct `viewBox` în metri.
- **Editor** (CRM): `resources/js/map-editor.js`. Adăugare, drag, resize, rotire (mâner, butoane și tasta R), duplicare, ștergere, numerotare automată, preț, electricitate, blocare, undo, grilă de snap, schiță de fundal cu opacitate.
- **Vizualizare** (producător, public, CRM): `resources/js/stand-map.js`. Pan, zoom cu rotița, pinch pe mobil, culori pe status.
- **Versionare**: `venue_layouts` (master, versiune incrementată la salvare) → `event_layouts` / `event_slots` (snapshot la crearea evenimentului).

### Digitizarea schițelor Târgu Mureș / Reghin

1. CRM → Hartă & standuri → locația → **Editor**.
2. Setezi dimensiunile reale ale terenului (lățime × adâncime, în metri), din schiță sau măsurate.
3. Încarci schița ca **fundal** (JPG/PNG/WebP; un PDF se exportă întâi ca imagine). Opacitatea se reglează din slider.
4. Desenezi standurile peste schiță: „+ Stand”, apoi trage, redimensionează și rotește. Pentru standuri identice folosești duplicarea.
5. „Numerotează” atribuie automat codurile `TM-01…` / `RG-01…` (prefixul = codul locației).
6. Completezi prețul (gol = „tarif la cerere”), electricitatea și eventualele spații blocate. Salvezi.
7. Setezi layout-ul ca **MASTER**. Evenimentele noi îl vor copia automat.

Layout-urile seed-uite sunt grile DEMO provizorii, marcate ca atare, până la primirea schițelor reale.

## 8. Automatizări (scheduler)

Vezi `CRON.md`. Toate rulează dintr-un singur cron cPanel.

## 9. Fișiere

| Tip | Disc | Acces |
|---|---|---|
| Contracte, anexe, documente producători, backup-uri, pachete update | `private` (`storage/app/private`) | numai prin `FileController` / Update Manager, cu verificare de permisiuni |
| Logo, copertă, galerie, fotografii eveniment, recenzii | `public` (`storage/app/public` → `public/storage`) | public; `.htaccess` interzice execuția de scripturi |

## 10. Update Manager

Parte fundamentală a arhitecturii, nu un adaos ulterior. Detalii în `UPDATE-PACKAGES.md` și `RELEASE-PROCESS.md`.

```
upload ZIP ─▶ validare (structură, manifest, versiune, SHA-256, Zip Slip, semnătură)
          ─▶ preflight ─▶ backup (fișiere + DB) ─▶ maintenance ─▶ install
          ─▶ migrate ─▶ optimize ─▶ verify ─▶ online
          (eșec după install ⇒ rollback automat al fișierelor, aplicația revine online)
```

## 11. Teste

`php artisan test` rulează 76 de teste (SQLite in-memory). Aceleași teste rulează și pe MariaDB:
`DB_CONNECTION=mariadb DB_DATABASE=… DB_USERNAME=… DB_PASSWORD=… php vendor/bin/phpunit`.

Acoperite: rezervare concurentă (inclusiv constraint DB), hold expirat, check-in unic, fidelitate la prag, recompensă ridicată o singură dată, tombolă doar cu eligibili, limbă RO/HU pentru clienți și producători, izolarea documentelor între producători, permisiuni, ZIP invalid, versiune incompatibilă, Zip Slip, symlink, semnătură, backup înainte de instalare, `.env` neatins, rollback, acoperirea traducerilor HU.

Verificat manual pe MariaDB 10.11: 26 de procese PHP paralele pe același stand dau exact 1 rezervare, 25 de refuzuri și 0 erori.
