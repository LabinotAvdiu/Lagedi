<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructures the users table:
 *  - Renames `name` → `last_name`
 *  - Drops legacy `api_token` column (replaced by Sanctum)
 *  - Adds: profile_image_url, failed_login_attempts, locked_until
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'last_name');

            $table->string('profile_image_url')->nullable()->after('last_name');

            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('profile_image_url');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');

            $table->dropColumn('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('last_name', 'name');
            $table->dropColumn([
                'profile_image_url',
                'failed_login_attempts',
                'locked_until',
            ]);
            $table->string('api_token', 80)->unique()->nullable();
        });
    }
};
