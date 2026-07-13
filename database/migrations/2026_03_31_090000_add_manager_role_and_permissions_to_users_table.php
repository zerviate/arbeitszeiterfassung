<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 32)->default('employee');
            $table->json('permissions')->nullable();

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['permissions', 'role']);
        });
    }
};
