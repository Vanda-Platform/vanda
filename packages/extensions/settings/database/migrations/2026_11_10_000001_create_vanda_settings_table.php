<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Values of Vanda\Settings\Settings classes: one row per class, named by its key() ("shop"),
 * with what was saved over the defaults; #[Secret] values are encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vanda_settings')) {
            return;
        }

        Schema::create('vanda_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->json('values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vanda_settings');
    }
};
