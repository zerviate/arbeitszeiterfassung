<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_requests', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 32);

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days_requested')->default(0);

            $table->string('reason', 1000)->nullable();

            $table->string('status', 32)->default('pending');

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_requests');
    }
};
