<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_variant_skus', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->json('option_values'); // e.g. {"Type": "Pepperoni", "Size": "Family"}
            $table->decimal('price', 10, 2);
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variant_skus');
    }
};
