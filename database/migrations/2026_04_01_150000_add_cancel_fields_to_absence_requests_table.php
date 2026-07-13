<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->foreignId('cancelled_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('reviewed_at');

            $table->index('cancelled_at');
        });

        DB::table('absence_requests')
            ->where('status', 'cancelled')
            ->update([
                'cancelled_by' => DB::raw('COALESCE(cancelled_by, reviewed_by)'),
                'cancelled_at' => DB::raw('COALESCE(cancelled_at, reviewed_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->dropIndex(['cancelled_at']);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });
    }
};
