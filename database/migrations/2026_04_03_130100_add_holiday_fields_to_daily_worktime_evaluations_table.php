<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_worktime_evaluations', function (Blueprint $table): void {
            $table->boolean('is_holiday')->default(false)->after('is_scheduled_workday');
            $table->string('holiday_name', 160)->nullable()->after('is_holiday');

            $table->index(['work_date', 'is_holiday']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_worktime_evaluations', function (Blueprint $table): void {
            $table->dropIndex('daily_worktime_evaluations_work_date_is_holiday_index');
            $table->dropColumn(['is_holiday', 'holiday_name']);
        });
    }
};
