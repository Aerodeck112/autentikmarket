# RELEASE-PROCESS: cum se generează o versiune nouă

> Când se cere „**Pregătește versiunea 1.4.0**”, se urmează exact pașii de mai jos. Rezultatul este
> `releases/dist/autentikmarket-update-1.4.0.zip`, compatibil direct cu Update Manager-ul.

## 0. Pregătire (o singură dată, pe mașina de dezvoltare)

- PHP 8.3+, Composer 2, Node 20+ (Node se folosește **doar** pentru build, niciodată pe server).
- Opțional, semnarea pachetelor:
  ```bash
  php artisan update:keygen
  ```
  - cheia **publică** se pune pe server în `.env`: `UPDATE_PUBLIC_KEY=...` și, opțional, `UPDATE_REQUIRE_SIGNATURE=true`;
  - cheia **secretă** se păstrează în afara repo-ului (ex. `~/.autentik/update-signing.key`). Nu se comite niciodată.

## 1. Dezvoltare

1. Implementați modificarea. Testele trec: `php artisan test` și `vendor/bin/pint --test`.
2. Modificările de bază de date se fac **doar prin migrații noi, incrementale și ne-distructive** (`MIGRATION.md`).
3. Datele de sistem noi (template-uri de notificare RO/HU, permisiuni, task-uri) se adaugă în seeder-ele idempotente **și** se apelează dintr-o migrație nouă (ex. `2026_12_01_000000_add_new_templates.php` care rulează `(new NotificationTemplateSeeder)->run()`).
4. Orice text nou din UI trebuie să aibă traducere în `lang/hu.json` (testul `TranslationCoverageTest`).
5. Dependențe Composer noi: actualizați `composer.lock`. Builder-ul detectează schimbarea și include automat `vendor/` în pachet.

## 2. Versiunea

- Semantic Versioning: `MAJOR.MINOR.PATCH`
  - PATCH (1.4.1): corecturi, fără schimbări de schemă incompatibile
  - MINOR (1.5.0): funcții noi, migrații aditive
  - MAJOR (2.0.0): schimbări majore. `minimum_version` se poate ridica, ca să nu se sară peste versiuni.
- Actualizați fișierul `VERSION` (ex. `1.4.0`).
- Scrieți notele de release:
  - `releases/notes/1.4.0.md` (RO)
  - `releases/notes/1.4.0.hu.md` (HU)
- Commit: `git commit -am "Release 1.4.0"`.

## 3. Build

```bash
tools/build-release.sh 1.4.0
# cu semnătură:
tools/build-release.sh 1.4.0 --sign-key-file ~/.autentik/update-signing.key
# variante:
#   --from=1.2.0         versiunea de bază (implicit: ultimul inventar din releases/manifests)
#   --min=1.2.0          minimum_version (implicit = --from)
#   --with-vendor        forțează includerea vendor/
#   --without-vendor     exclude vendor/
```

Scriptul:
1. exportă HEAD într-un director curat (`git archive`), deci fără fișiere locale și fără `.env`;
2. rulează `composer install --no-dev --optimize-autoloader` și elimină directoarele `.git` din `vendor/`;
3. rulează `npm ci && npm run build` (asset-uri compilate, minificate, cu hash);
4. rulează `php artisan release:build 1.4.0`, care:
   - colectează fișierele din căile aplicației (lista albă);
   - calculează SHA-256 pentru fiecare fișier;
   - compară cu inventarul versiunii anterioare (`releases/manifests/1.3.x.json`) ca să calculeze lista `delete`;
   - detectează migrațiile noi și schimbarea `composer.lock` (pentru `vendor/`);
   - scrie `update.json` + checksum, notele de release, semnătura (opțional);
5. copiază în repo:
   - `releases/dist/autentikmarket-update-1.4.0.zip` (nu se comite)
   - `releases/manifests/1.4.0.json` (**se comite**: e baza pentru următorul update)

```bash
git add releases/manifests/1.4.0.json releases/notes/1.4.0*.md
git commit -m "Inventar release 1.4.0"
git tag v1.4.0
```

## 4. Verificare înainte de livrare (recomandat)

Pe o copie de staging a aplicației (sau local, cu o bază de test):

```bash
php artisan update:install releases/dist/autentikmarket-update-1.4.0.zip --yes
```

Verificați: versiunea nouă în CRM → Sistem, `.env` neschimbat, paginile principale, `php artisan migrate:status`.

## 5. Livrare

Administratorul intră în **CRM → Sistem → Actualizări → Încarcă actualizare**, vede noutățile și apasă **INSTALARE ACTUALIZARE**.

## Pachetul de instalare inițială

```bash
tools/build-release.sh 1.0.0 --install
```

Produce `autentikmarket-install-1.0.0.zip` (aplicația completă, cu `vendor/` și `public/build/`) și inventarul `releases/manifests/1.0.0.json`, baza primului update.

## Checklist „Pregătește versiunea X.Y.Z”

- [ ] modificările implementate, testele trec, Pint OK
- [ ] migrații incrementale (fără drop-uri neconfirmate)
- [ ] traduceri HU pentru toate textele noi
- [ ] `VERSION` = X.Y.Z
- [ ] `releases/notes/X.Y.Z.md` + `.hu.md`
- [ ] commit
- [ ] `tools/build-release.sh X.Y.Z [--sign-key-file …]`
- [ ] commit `releases/manifests/X.Y.Z.json` + tag `vX.Y.Z`
- [ ] test de instalare pe staging
- [ ] livrare ZIP către administrator
