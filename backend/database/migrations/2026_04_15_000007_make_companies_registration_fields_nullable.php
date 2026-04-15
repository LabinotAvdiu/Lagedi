<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes non-essential company columns nullable so a company can be created
 * during user registration with minimal data (name, address, city) and
 * completed later via a company profile update flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('postal_code', 20)->nullable()->change();
        });

        // Make the spatial POINT column nullable so companies can be created
        // without coordinates at registration time (filled in later).
        // MySQL requires dropping the spatial index before allowing NULL on a spatial column.
        DB::statement('DROP INDEX companies_location_spatialindex ON companies');
        DB::statement('ALTER TABLE companies MODIFY location POINT NULL SRID 4326');
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('postal_code', 20)->nullable(false)->change();
        });

        DB::statement('ALTER TABLE companies MODIFY location POINT NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX companies_location_spatialindex ON companies(location)');
    }
};
