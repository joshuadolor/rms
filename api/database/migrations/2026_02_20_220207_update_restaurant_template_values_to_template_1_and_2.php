<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Map legacy template IDs to template-1 / template-2 (generic-templates).
     */
    public function up(): void
    {
        DB::table('restaurants')->where('template', 'default')->update(['template' => 'template-1']);
        DB::table('restaurants')->where('template', 'minimal')->update(['template' => 'template-2']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('restaurants')->where('template', 'template-1')->update(['template' => 'default']);
        DB::table('restaurants')->where('template', 'template-2')->update(['template' => 'minimal']);
    }
};
