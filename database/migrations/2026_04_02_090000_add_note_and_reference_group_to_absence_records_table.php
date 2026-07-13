<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_records', function (Blueprint $table): void {
            $table->string('note', 1000)->nullable()->after('source');
            $table->string('reference_group', 100)->nullable()->after('note');

            $table->index('reference_group');
            $table->index(['user_id', 'reference_group']);
        });
    }

    public function down(): void
    {
        Schema::table('absence_records', function (Blueprint $table): void {
            $table->dropIndex(['reference_group']);
            $table->dropIndex(['user_id', 'reference_group']);
            $table->dropColumn(['note', 'reference_group']);
        });
    }
};
