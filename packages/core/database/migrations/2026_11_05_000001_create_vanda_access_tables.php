<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions in Orchid's layout: an Orchid database already has
 * these tables and columns, so every step is skipped when it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_users')) {
            Schema::create('role_users', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['user_id', 'role_id']);
                $table->index('role_id');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('permissions')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_users');
        Schema::dropIfExists('roles');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }
    }
};
