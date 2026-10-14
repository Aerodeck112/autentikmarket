<?php

namespace App\Support;

/**
 * Definiția centrală a permisiunilor și a rolurilor implicite.
 * Sincronizată în DB de SystemDataSeeder (idempotent; rulat și de migrații la update).
 */
class Rbac
{
    /** name => [group, ro, hu] */
    public const PERMISSIONS = [
        'crm.access' => ['crm', 'Acces CRM', 'CRM hozzáférés'],
        'dashboard.view' => ['crm', 'Vizualizare dashboard', 'Irányítópult megtekintése'],
        'events.view' => ['events', 'Vizualizare evenimente', 'Események megtekintése'],
        'events.manage' => ['events', 'Administrare evenimente', 'Események kezelése'],
        'maps.manage' => ['events', 'Editor hărți & locații', 'Térképszerkesztő és helyszínek'],
        'reservations.manage' => ['events', 'Administrare rezervări standuri', 'Standfoglalások kezelése'],
        'producers.view' => ['producers', 'Vizualizare producători', 'Termelők megtekintése'],
        'producers.manage' => ['producers', 'Editare producători', 'Termelők szerkesztése'],
        'producers.approve' => ['producers', 'Verificare / aprobare producători', 'Termelők ellenőrzése / jóváhagyása'],
        'visitors.view' => ['visitors', 'Vizualizare vizitatori', 'Látogatók megtekintése'],
        'visitors.manage' => ['visitors', 'Editare vizitatori', 'Látogatók szerkesztése'],
        'checkin.perform' => ['visitors', 'Check-in vizitatori', 'Látogatók beléptetése'],
        'rewards.redeem' => ['visitors', 'Validare ridicare cadouri', 'Ajándékátadás rögzítése'],
        'loyalty.manage' => ['visitors', 'Reguli de fidelitate', 'Hűségprogram szabályai'],
        'raffles.manage' => ['raffles', 'Administrare tombole', 'Tombolák kezelése'],
        'raffles.draw' => ['raffles', 'Extragere tombolă', 'Tombolahúzás'],
        'raffles.validate' => ['raffles', 'Validare premii tombolă', 'Tombolanyeremény átadása'],
        'reviews.moderate' => ['content', 'Moderare recenzii', 'Értékelések moderálása'],
        'contracts.manage' => ['documents', 'Contracte & anexe', 'Szerződések és mellékletek'],
        'tasks.view' => ['tasks', 'Vizualizare task-uri', 'Feladatok megtekintése'],
        'tasks.manage' => ['tasks', 'Administrare task-uri', 'Feladatok kezelése'],
        'blog.manage' => ['content', 'Administrare blog', 'Blog kezelése'],
        'notifications.manage' => ['system', 'Automatizări & notificări', 'Automatizálások és értesítések'],
        'reports.view' => ['reports', 'Rapoarte & statistici', 'Jelentések és statisztikák'],
        'exports.run' => ['reports', 'Export CSV / XLSX', 'CSV / XLSX export'],
        'settings.manage' => ['system', 'Setări', 'Beállítások'],
        'users.manage' => ['system', 'Utilizatori & roluri', 'Felhasználók és szerepkörök'],
        'audit.view' => ['system', 'Audit log', 'Audit napló'],
        'system.view' => ['system', 'Informații sistem', 'Rendszerinformációk'],
        'system.update' => ['system', 'Instalare actualizări (ZIP)', 'Frissítések telepítése (ZIP)'],
    ];

    public static function rolePermissions(): array
    {
        $all = array_keys(self::PERMISSIONS);

        return [
            'visitor' => [],
            'producer' => [],
            'staff' => ['checkin.perform', 'rewards.redeem', 'raffles.validate', 'producers.view', 'visitors.view'],
            'organizer' => [
                'crm.access', 'dashboard.view', 'events.view', 'events.manage', 'maps.manage', 'reservations.manage',
                'producers.view', 'producers.manage', 'producers.approve', 'visitors.view', 'checkin.perform',
                'rewards.redeem', 'raffles.manage', 'raffles.validate', 'reviews.moderate', 'contracts.manage',
                'tasks.view', 'tasks.manage', 'blog.manage', 'reports.view', 'exports.run',
            ],
            // Administrator: tot, mai puțin instalarea actualizărilor.
            'admin' => array_values(array_diff($all, ['system.update'])),
            'super_admin' => $all,
        ];
    }
}
