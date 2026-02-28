<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_legal', function (Blueprint $table) {
            $table->id();
            $table->longText('terms_of_service')->nullable();
            $table->longText('privacy_policy')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_legal');
    }
};
