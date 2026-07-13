<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_balances', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            $table->decimal('annual_entitlement_days', 6, 2)->default(0);
            $table->decimal('carryover_days', 6, 2)->default(0);
            $table->decimal('manual_adjustment_days', 6, 2)->default(0);

            $table->string('note', 1000)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'year']);
            $table->index(['year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_balances');
    }
};
