<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('email');
            $table->unsignedInteger('city')->nullable()->after('country');
            $table->string('vat')->nullable()->after('city');
            $table->string('status')->default('active')->after('vat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'vat', 'status']);
        });
    }
};
