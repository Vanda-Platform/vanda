<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What users did in the panel (Vanda\Activity): who changed which record, on which screen,
 * with which button, and the values before and after.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vanda_activity')) {
            return;
        }

        Schema::create('vanda_activity', function (Blueprint $table) {
            $table->id();
            $table->string('event', 32);
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_label')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id', 64)->nullable();
            $table->string('causer_name')->nullable();
            $table->string('screen')->nullable();
            $table->string('action')->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('diff')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index('event');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vanda_activity');
    }
};
