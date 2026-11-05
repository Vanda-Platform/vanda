<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->string('slug');
            $table->string('status')->default('draft');
            $table->string('template')->default('page');
            $table->longText('content')->nullable();
            $table->text('notes')->nullable();
            $table->text('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
