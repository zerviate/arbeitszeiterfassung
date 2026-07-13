<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatesExist = DB::table('absence_records')
            ->select('user_id', 'absence_date', DB::raw('COUNT(*) as row_count'))
            ->groupBy('user_id', 'absence_date')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicatesExist) {
            throw new RuntimeException('absence_records enthaelt doppelte user_id/absence_date Kombinationen. Bitte Konflikte bereinigen, bevor die neue Unique-Constraint gesetzt wird.');
        }

        Schema::table('absence_records', function (Blueprint $table): void {
            if (Schema::hasIndex('absence_records', ['user_id', 'type', 'absence_date'], 'unique')) {
                $table->dropUnique(['user_id', 'type', 'absence_date']);
            }

            if (! Schema::hasIndex('absence_records', ['user_id', 'absence_date'], 'unique')) {
                $table->unique(['user_id', 'absence_date']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('absence_records', function (Blueprint $table): void {
            if (Schema::hasIndex('absence_records', ['user_id', 'absence_date'], 'unique')) {
                $table->dropUnique(['user_id', 'absence_date']);
            }

            if (! Schema::hasIndex('absence_records', ['user_id', 'type', 'absence_date'], 'unique')) {
                $table->unique(['user_id', 'type', 'absence_date']);
            }
        });
    }
};
