<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Make user_id nullable so walk-in appointments don't need an account
            $table->foreignId('user_id')->nullable()->change();

            // Walk-in client details (populated when is_walk_in = true)
            $table->boolean('is_walk_in')->default(false)->after('notes');
            $table->string('walk_in_first_name')->nullable()->after('is_walk_in');
            $table->string('walk_in_last_name')->nullable()->after('walk_in_first_name');
            $table->string('walk_in_phone')->nullable()->after('walk_in_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['is_walk_in', 'walk_in_first_name', 'walk_in_last_name', 'walk_in_phone']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
