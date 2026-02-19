<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_id')->nullable()->change();
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_id')->nullable(false)->change();
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
