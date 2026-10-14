<?php

return [
    /*
    | Limbile platformei. Ordinea contează: prima este limba implicită.
    */
    'locales' => ['ro', 'hu'],

    'default_locale' => 'ro',

    /*
    | Durata hold-ului temporar pe un stand (minute). Suprascris din CRM → Setări.
    */
    'stand_hold_minutes' => (int) env('STAND_HOLD_MINUTES', 15),

    /*
    | Intervalul de polling al hărții producătorului (secunde). Fără WebSocket.
    */
    'map_poll_seconds' => 20,

    /*
    | Versiuni curente ale documentelor legale (pentru consimțăminte GDPR).
    */
    'legal_versions' => [
        'terms' => '1.0',
        'privacy' => '1.0',
        'marketing' => '1.0',
        'newsletter' => '1.0',
    ],

    /*
    | Upload-uri: limite (KB) și tipuri permise.
    */
    'uploads' => [
        'image_max_kb' => 8192,
        'document_max_kb' => 10240,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'document_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'background_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
    ],

    /*
    | Rolurile sistemului (cheie => [ro, hu]).
    */
    'roles' => [
        'visitor' => ['Client / Vizitator', 'Ügyfél / Látogató'],
        'producer' => ['Producător / Artizan', 'Termelő / Kézműves'],
        'staff' => ['Staff eveniment', 'Rendezvény személyzet'],
        'organizer' => ['Organizator', 'Szervező'],
        'admin' => ['Administrator', 'Adminisztrátor'],
        'super_admin' => ['Super Admin', 'Szuper admin'],
    ],
];
