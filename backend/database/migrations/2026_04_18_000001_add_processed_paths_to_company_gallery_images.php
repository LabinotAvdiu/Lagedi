<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_gallery_images', function (Blueprint $table) {
            $table->string('thumbnail_path', 500)->nullable()->after('image_path');
            $table->string('medium_path', 500)->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_gallery_images', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_path', 'medium_path']);
        });
    }
};
