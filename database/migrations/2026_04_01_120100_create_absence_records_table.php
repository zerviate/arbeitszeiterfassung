<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_records', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 32);
            $table->date('absence_date');

            $table->string('source', 32)->default('request_approved');

            $table->foreignId('absence_request_id')->nullable()->constrained('absence_requests')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'type', 'absence_date']);
            $table->index(['user_id', 'absence_date']);
            $table->index(['type', 'absence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_records');
    }
};
