<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->timestamps();

            $table->unique(['restaurant_id', 'locale']);
        });

        // Seed existing restaurants with their default_locale so they have at least one language.
        $restaurants = DB::table('restaurants')->select('id', 'default_locale')->get();
        $now = now();
        foreach ($restaurants as $r) {
            DB::table('restaurant_languages')->insert([
                'restaurant_id' => $r->id,
                'locale' => $r->default_locale ?? 'en',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_languages');
    }
};
