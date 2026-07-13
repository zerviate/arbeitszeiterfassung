<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_calendar_entries', function (Blueprint $table): void {
            $table->id();

            $table->date('holiday_date')->unique();
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['holiday_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_calendar_entries');
    }
};
