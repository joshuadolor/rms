<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('sort_order');
            $table->uuid('source_menu_item_uuid')->nullable()->after('price');
            $table->decimal('price_override', 10, 2)->nullable()->after('source_menu_item_uuid');
            $table->json('translation_overrides')->nullable()->after('price_override');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'source_menu_item_uuid', 'price_override', 'translation_overrides']);
        });
    }
};
