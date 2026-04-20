<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('cv_original_name')->nullable()->after('cv_path');
            $table->string('certificate_path')->nullable()->after('cv_original_name');
            $table->string('certificate_original_name')->nullable()->after('certificate_path');
            $table->string('id_document_path')->nullable()->after('certificate_original_name');
            $table->string('id_document_original_name')->nullable()->after('id_document_path');
            $table->string('passport_photo_path')->nullable()->after('id_document_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'cv_original_name',
                'certificate_path',
                'certificate_original_name',
                'id_document_path',
                'id_document_original_name',
                'passport_photo_path',
            ]);
        });
    }
};