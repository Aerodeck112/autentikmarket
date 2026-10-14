# MIGRATION: reguli pentru migrațiile bazei de date

Actualizările rulează doar `php artisan migrate --force`. O migrație greșită poate distruge date reale, de aceea sunt obligatorii regulile de mai jos.

## Reguli

1. **Doar migrații noi.** Nu modificați niciodată o migrație deja livrată: pe server ea a rulat deja și nu va mai rula.
2. **Aditiv înainte de toate.** Tabele noi, coloane noi `nullable()` sau cu `default()`, indexuri noi.
3. **Fără ștergeri directe.** Pentru a elimina o coloană sau un tabel:
   - versiunea N: codul nu mai folosește coloana (coloana rămâne);
   - versiunea N+1 (sau mai târziu): migrația de ștergere, marcată în notele de release. Update Manager-ul o detectează și cere confirmare explicită.
4. **Redenumiri în doi pași:** coloană nouă, copiere date, cod migrat, abia apoi ștergerea veche (ca la punctul 3).
5. **Compatibilitate cu codul anterior.** După o migrație, codul versiunii precedente trebuie să funcționeze în continuare. Rollback-ul automat restaurează doar fișierele, nu și schema.
6. **Portabilitate MySQL/MariaDB.** Fără SQL specific unui singur motor. Pentru operații speciale folosiți `DB::connection()->getDriverName()`.
7. **Date de sistem** (template-uri, permisiuni, categorii implicite) prin seeder-e **idempotente** (`firstOrCreate` / `updateOrCreate` fără a suprascrie textele editate de admin), apelate din migrație.
8. **Volume mari**: actualizări în loturi (`chunkById`), ca să nu depășiți timpul de execuție al unui request (pasul `migrate` rulează într-un request HTTP).
9. `down()` poate exista pentru development, dar Update Manager-ul **nu** rulează niciodată `migrate:rollback`.

## Exemplu: coloană nouă

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->string('tiktok')->nullable()->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('producers', fn (Blueprint $t) => $t->dropColumn('tiktok'));
    }
};
```

## Exemplu: template de notificare nou

```php
return new class extends Migration {
    public function up(): void
    {
        (new \Database\Seeders\NotificationTemplateSeeder)->run(); // idempotent
    }

    public function down(): void {}
};
```

## Verificare

```bash
php artisan migrate:status
php artisan test                       # SQLite
DB_CONNECTION=mariadb … php vendor/bin/phpunit   # MariaDB
```
