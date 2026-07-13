<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_worktime_evaluations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();

            $table->boolean('is_scheduled_workday')->default(false);
            $table->unsignedInteger('target_minutes')->default(0);
            $table->unsignedInteger('actual_minutes')->default(0);
            $table->unsignedInteger('vacation_minutes')->default(0);
            $table->unsignedInteger('sick_leave_minutes')->default(0);
            $table->integer('balance_minutes')->default(0);

            $table->string('day_status', 50)->nullable();
            $table->string('traffic_light', 20)->nullable();
            $table->json('flags')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date', 'traffic_light']);
            $table->index(['day_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_worktime_evaluations');
    }
};
