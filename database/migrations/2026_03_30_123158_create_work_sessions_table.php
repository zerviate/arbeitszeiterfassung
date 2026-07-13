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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('gross_minutes')->default(0);

            $table->enum('status', ['open', 'closed', 'corrected', 'invalid'])->default('open');

            $table->foreignId('opened_by_event_id')->nullable()->constrained('time_events')->nullOnDelete();
            $table->foreignId('closed_by_event_id')->nullable()->constrained('time_events')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
