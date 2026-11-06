<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ediții din trecut adăugate retroactiv („arhivă”): încheiate fără fluxul de după târg
 * (fără mulțumiri, cereri de recenzie sau task-uri pentru o ediție de acum luni/ani).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'archived_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('post_event_processed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
