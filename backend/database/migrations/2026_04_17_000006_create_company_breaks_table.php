<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->unsigned()->nullable(); // null = every day
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_breaks');
    }
};
