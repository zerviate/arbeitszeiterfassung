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
        Schema::create('break_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_session_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('minutes')->default(0);

            $table->enum('status', ['open', 'closed', 'invalid'])->default('open');

            $table->foreignId('started_by_event_id')->nullable()->constrained('time_events')->nullOnDelete();
            $table->foreignId('ended_by_event_id')->nullable()->constrained('time_events')->nullOnDelete();

            $table->timestamps();

            $table->index(['work_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_sessions');
    }
};
