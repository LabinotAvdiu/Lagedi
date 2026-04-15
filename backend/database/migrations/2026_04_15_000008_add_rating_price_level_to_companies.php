<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->unsigned()->default(0.00)->after('profile_image_url');
            $table->unsignedInteger('review_count')->default(0)->after('rating');
            $table->tinyInteger('price_level')->unsigned()->default(2)->comment('1 = budget, 2 = moderate, 3 = upscale, 4 = luxury')->after('review_count');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['rating', 'review_count', 'price_level']);
        });
    }
};
