<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOCALES = ['en', 'es', 'ar'];

    public function up(): void
    {
        Schema::table('site_legal', function (Blueprint $table) {
            $table->longText('terms_of_service_en')->nullable()->after('privacy_policy');
            $table->longText('privacy_policy_en')->nullable()->after('terms_of_service_en');
            $table->longText('terms_of_service_es')->nullable()->after('privacy_policy_en');
            $table->longText('privacy_policy_es')->nullable()->after('terms_of_service_es');
            $table->longText('terms_of_service_ar')->nullable()->after('privacy_policy_es');
            $table->longText('privacy_policy_ar')->nullable()->after('terms_of_service_ar');
        });

        // Migrate existing single columns into en
        $rows = DB::table('site_legal')->get();
        foreach ($rows as $row) {
            DB::table('site_legal')
                ->where('id', $row->id)
                ->update([
                    'terms_of_service_en' => $row->terms_of_service,
                    'privacy_policy_en' => $row->privacy_policy,
                ]);
        }

        Schema::table('site_legal', function (Blueprint $table) {
            $table->dropColumn(['terms_of_service', 'privacy_policy']);
        });
    }

    public function down(): void
    {
        Schema::table('site_legal', function (Blueprint $table) {
            $table->longText('terms_of_service')->nullable()->after('id');
            $table->longText('privacy_policy')->nullable()->after('terms_of_service');
        });

        $rows = DB::table('site_legal')->get();
        foreach ($rows as $row) {
            DB::table('site_legal')
                ->where('id', $row->id)
                ->update([
                    'terms_of_service' => $row->terms_of_service_en ?? '',
                    'privacy_policy' => $row->privacy_policy_en ?? '',
                ]);
        }

        Schema::table('site_legal', function (Blueprint $table) {
            $table->dropColumn([
                'terms_of_service_en', 'privacy_policy_en',
                'terms_of_service_es', 'privacy_policy_es',
                'terms_of_service_ar', 'privacy_policy_ar',
            ]);
        });
    }
};
