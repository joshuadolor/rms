<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('color')->default('#6b7280');
            $table->string('icon')->default('');
            $table->string('text');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Custom tags: unique text per user (application also enforces; default tags user_id=null)
            $table->unique(['user_id', 'text']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_tags');
    }
};
