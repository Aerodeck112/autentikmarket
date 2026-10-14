<?php

namespace App\Services\Updates;

/**
 * Operațiile de runtime folosite de Update Manager (abstractizate pentru teste).
 */
interface ApplicationRuntime
{
    public function down(): void;

    public function up(): void;

    public function isDown(): bool;

    /** @return string output */
    public function migrate(): string;

    /** @return list<string> migrații nerulate */
    public function pendingMigrations(): array;

    public function clearCaches(): void;

    public function optimize(): void;

    public function checkDatabase(): bool;
}
