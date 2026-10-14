<?php

namespace Tests\Support;

use App\Services\Updates\ApplicationRuntime;
use App\Services\Updates\UpdateException;

/** Runtime fals pentru testele Update Manager-ului (fără artisan down/migrate/optimize reale). */
class FakeRuntime implements ApplicationRuntime
{
    public bool $down = false;

    public array $calls = [];

    public bool $failMigrate = false;

    public function down(): void
    {
        $this->down = true;
        $this->calls[] = 'down';
    }

    public function up(): void
    {
        $this->down = false;
        $this->calls[] = 'up';
    }

    public function isDown(): bool
    {
        return $this->down;
    }

    public function migrate(): string
    {
        $this->calls[] = 'migrate';
        if ($this->failMigrate) {
            throw new UpdateException('Migrație eșuată (simulare).');
        }

        return 'Nothing to migrate.';
    }

    public function pendingMigrations(): array
    {
        return [];
    }

    public function clearCaches(): void
    {
        $this->calls[] = 'clear';
    }

    public function optimize(): void
    {
        $this->calls[] = 'optimize';
    }

    public function checkDatabase(): bool
    {
        return true;
    }
}
