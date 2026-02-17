<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_translations');
    }
};
