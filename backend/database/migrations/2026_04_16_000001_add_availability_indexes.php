<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(
                ['company_user_id', 'date', 'status'],
                'idx_appointments_emp_date_status',
            );
        });

        Schema::table('company_user', function (Blueprint $table) {
            $table->index(
                ['company_id', 'is_active'],
                'idx_company_user_company_active',
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_emp_date_status');
        });

        Schema::table('company_user', function (Blueprint $table) {
            $table->dropIndex('idx_company_user_company_active');
        });
    }
};
