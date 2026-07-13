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
        Schema::create('time_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date');

            $table->json('old_values')->nullable();
            $table->json('new_values');

            $table->string('reason', 1000);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_corrections');
    }
};
