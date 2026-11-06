<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Telefon normalizat (doar cifre, cu prefix de țară) pentru logare și identificare cu numărul de telefon. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone_key')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone_key', 20)->nullable()->after('phone')->index();
            });
        }
        DB::table('users')->whereNotNull('phone')->orderBy('id')->select(['id', 'phone'])->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update(['phone_key' => Phone::key($row->phone)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_key']);
            $table->dropColumn('phone_key');
        });
    }
};
