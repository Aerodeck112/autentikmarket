<?php

namespace Tests\Unit;

use App\Services\Updates\DestructiveMigrationScanner;
use PHPUnit\Framework\TestCase;

class DestructiveMigrationScannerTest extends TestCase
{
    public function test_detects_drops_only_in_up(): void
    {
        $safe = "<?php return new class { public function up(): void { Schema::table('x', fn(\$t) => \$t->string('a')->nullable()); } public function down(): void { Schema::dropIfExists('x'); } };";
        $this->assertSame([], DestructiveMigrationScanner::scan($safe));

        $bad = "<?php return new class { public function up(): void { Schema::table('x', fn(\$t) => \$t->dropColumn('a')); } };";
        $this->assertNotEmpty(DestructiveMigrationScanner::scan($bad));

        $commented = "<?php return new class { public function up(): void { // Schema::drop('x');\n } };";
        $this->assertSame([], DestructiveMigrationScanner::scan($commented));
    }
}
