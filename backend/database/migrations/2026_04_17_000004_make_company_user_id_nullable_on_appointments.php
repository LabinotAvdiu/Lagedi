<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Drop the non-nullable FK constraint, then re-add as nullable
            $table->dropForeign(['company_user_id']);
            $table->unsignedBigInteger('company_user_id')->nullable()->change();
            $table->foreign('company_user_id')
                  ->references('id')
                  ->on('company_user')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['company_user_id']);
            $table->unsignedBigInteger('company_user_id')->nullable(false)->change();
            $table->foreign('company_user_id')
                  ->references('id')
                  ->on('company_user')
                  ->cascadeOnDelete();
        });
    }
};
