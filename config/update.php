<?php

return [
    /*
    | Identificatorul aplicației. Pachetele cu alt "application" sunt respinse.
    */
    'application_id' => 'autentikmarket',

    /*
    | Directorul aplicației în care se instalează fișierele. Configurabil doar pentru teste.
    */
    'target_path' => env('UPDATE_TARGET_PATH') ?: base_path(),

    /*
    | Dimensiunea maximă a pachetului (MB).
    */
    'max_package_mb' => (int) env('UPDATE_MAX_PACKAGE_MB', 200),

    /*
    | Spațiu liber minim pe disc înainte de instalare (MB).
    */
    'min_free_disk_mb' => (int) env('UPDATE_MIN_FREE_DISK_MB', 200),

    /*
    | Semnătură Ed25519 (libsodium). Cheia PUBLICĂ (base64) a echipei de release.
    | Dacă require_signature = true, pachetele nesemnate sau cu semnătură invalidă sunt respinse.
    */
    'public_key' => env('UPDATE_PUBLIC_KEY', ''),
    'require_signature' => (bool) env('UPDATE_REQUIRE_SIGNATURE', false),

    /*
    | Backup bază de date înainte de update (dump PHP pur, gzip).
    */
    'backup_database' => (bool) env('UPDATE_BACKUP_DATABASE', true),

    /*
    | Câte backup-uri de update păstrăm (cele mai vechi sunt șterse automat).
    */
    'keep_backups' => (int) env('UPDATE_KEEP_BACKUPS', 5),

    /*
    | Căi relative (la rădăcina aplicației) în care un pachet ARE VOIE să scrie.
    | Orice altă cale este respinsă la validare.
    */
    'allowed_paths' => [
        'app/',
        'bootstrap/app.php',
        'bootstrap/providers.php',
        'config/',
        'database/migrations/',
        'database/seeders/',
        'database/factories/',
        'lang/',
        'public/build/',
        'public/images/',
        'public/icons/',
        'public/index.php',
        'public/install.php',
        'public/.htaccess',
        'public/favicon.ico',
        'public/favicon.svg',
        'public/manifest.webmanifest',
        'public/sw.js',
        'resources/',
        'routes/',
        'vendor/',
        'artisan',
        'composer.json',
        'composer.lock',
        'VERSION',
        'docs/',
    ],

    /*
    | Căi PROTEJATE: nu sunt scrise și nici șterse NICIODATĂ, chiar dacă apar în pachet.
    | (.env, upload-uri, storage privat, log-uri, backup-uri).
    */
    'protected_paths' => [
        '.env',
        '.env.',
        'storage/',
        'public/storage',
        'public/uploads/',
        'bootstrap/cache/',
        '.htaccess',
        '.git/',
        'database/database.sqlite',
    ],

    /*
    | Fișiere permise în rădăcina arhivei (în afara directorului files/).
    */
    'root_entries' => ['update.json', 'update.sig', 'release-notes.md', 'release-notes.hu.md'],
];
