<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_time_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date');

            $table->unsignedInteger('gross_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('net_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);

            $table->boolean('has_open_entries')->default(false);
            $table->boolean('has_manual_corrections')->default(false);

            $table->json('violation_flags')->nullable();

            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_time_summaries');
    }
};
