<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ENUM modification — extend values to include rejected and no_show
        DB::statement(
            "ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','confirmed','cancelled','completed','rejected','no_show') NOT NULL DEFAULT 'pending'"
        );

        // Add composite index for capacity-based availability queries
        DB::statement(
            'ALTER TABLE appointments ADD INDEX appointments_company_service_date_idx (company_id, service_id, date, start_time)'
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending'"
        );

        DB::statement(
            'ALTER TABLE appointments DROP INDEX appointments_company_service_date_idx'
        );
    }
};
