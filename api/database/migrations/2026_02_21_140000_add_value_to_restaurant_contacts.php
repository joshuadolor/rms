<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds unified `value` (phone number or URL); backfills from `number`; makes `number` nullable for backward compatibility.
     */
    public function up(): void
    {
        Schema::table('restaurant_contacts', function (Blueprint $table) {
            $table->string('value', 500)->nullable()->after('type');
        });

        DB::table('restaurant_contacts')->whereNotNull('number')->update([
            'value' => DB::raw('`number`'),
        ]);

        Schema::table('restaurant_contacts', function (Blueprint $table) {
            $table->string('number', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('restaurant_contacts')->whereNull('number')->whereNotNull('value')->update([
            'number' => DB::raw('`value`'),
        ]);
        Schema::table('restaurant_contacts', function (Blueprint $table) {
            $table->dropColumn('value');
            $table->string('number', 50)->nullable(false)->change();
        });
    }
};
