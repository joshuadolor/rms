<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            // Token that this record was rotated to (successor). Used for short grace-window reuse.
            $table->foreignId('rotated_to_id')
                ->nullable()
                ->after('rotated_from_id')
                ->constrained('refresh_tokens')
                ->nullOnDelete();

            $table->index(['rotated_to_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropIndex(['rotated_to_id', 'revoked_at']);
            $table->dropConstrainedForeignId('rotated_to_id');
        });
    }
};

