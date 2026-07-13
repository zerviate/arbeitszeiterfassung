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
        Schema::create('time_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', [
                'clock_in',
                'clock_out',
                'break_start',
                'break_end',
                'manual_entry',
                'manual_correction'
            ]);

            $table->timestamp('occurred_at');
            $table->date('work_date');

            $table->enum('source', ['web', 'mobile', 'terminal', 'admin', 'import'])->default('web');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_events');
    }
};
