<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('guard_name');
            $table->timestamp('permissions_initialized_at')->nullable()->after('description');
            $table->timestamp('permissions_customized_at')->nullable()->after('permissions_initialized_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('operator_category', 20)->nullable()->after('role');
        });

        Schema::create('permission_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('role_name');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();

            $table->index(['role_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_change_logs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('operator_category');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['description', 'permissions_initialized_at', 'permissions_customized_at']);
        });
    }
};
