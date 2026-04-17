<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds indexes that cover the GET /api/companies listing query:
 *
 *   SELECT ... FROM companies
 *   WHERE gender IN (?, 'both')          -- covered by idx_gender
 *   AND LOWER(city) = ?                  -- covered by idx_city (existing) +
 *                                           a generated column approach is ideal
 *                                           but LOWER(city) hits the city index in MySQL 8
 *   ORDER BY rating DESC, name ASC       -- covered by idx_rating_name composite
 *   LIMIT 20;
 *
 * The FULLTEXT index on (name, city, address) already exists from the initial migration.
 * The `city` single-column index already exists. This migration only adds what is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Covers ORDER BY rating DESC, name ASC — avoids filesort on large tables.
            // MySQL uses this for the default listing (no filters) as well as when only
            // the gender or city filter is applied (index merge or filtered scan).
            $table->index(['rating', 'name'], 'idx_companies_rating_name');

            // Covers WHERE gender = ? OR gender = 'both' filter.
            $table->index('gender', 'idx_companies_gender');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_rating_name');
            $table->dropIndex('idx_companies_gender');
        });
    }
};
