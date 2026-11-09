<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each user chose in the panel and should find again (Vanda\Support\Preferences): the view of
 * a table (search, filters, sort, rows per page, page), kept per account, so it survives signing
 * out and follows the user to another browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vanda_preferences')) {
            return;
        }

        Schema::create('vanda_preferences', function (Blueprint $table) {
            $table->id();
            // The user's auth identifier as a string: ids and UUIDs alike.
            $table->string('user_id', 64);
            $table->string('key', 191);
            $table->json('value');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vanda_preferences');
    }
};
