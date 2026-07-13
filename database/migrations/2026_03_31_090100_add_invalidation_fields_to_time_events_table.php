<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_events', function (Blueprint $table): void {
            $table->timestamp('invalidated_at')->nullable()->index();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invalidation_reason', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('time_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invalidated_by');
            $table->dropColumn(['invalidated_at', 'invalidation_reason']);
        });
    }
};
