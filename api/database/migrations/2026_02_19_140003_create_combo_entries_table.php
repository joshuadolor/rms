<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('referenced_menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('menu_item_variant_skus')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('modifier_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_entries');
    }
};
