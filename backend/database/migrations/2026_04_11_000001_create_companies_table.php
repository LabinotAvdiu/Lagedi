<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phone', 20);
            $table->string('email');
            $table->string('address');
            $table->string('city', 100);
            $table->string('postal_code', 20);
            $table->string('country', 100)->default('France');
            $table->enum('gender', ['men', 'women', 'both'])->default('both');
            $table->timestamps();

            $table->index('city');
        });

        DB::statement('ALTER TABLE companies ADD location POINT NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX companies_location_spatialindex ON companies(location)');
        DB::statement('CREATE FULLTEXT INDEX companies_fulltext ON companies(name, city, address)');
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
