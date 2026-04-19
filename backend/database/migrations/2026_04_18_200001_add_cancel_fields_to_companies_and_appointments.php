<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feature 1 — Annulation client : délai minimum configurable par salon
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('min_cancel_hours')->default(2)->after('review_count');
        });

        // Feature 1 — Annulation client : traçabilité + raison
        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestamp('cancelled_by_client_at')->nullable()->after('status');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by_client_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_by_client_at', 'cancellation_reason']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('min_cancel_hours');
        });
    }
};
