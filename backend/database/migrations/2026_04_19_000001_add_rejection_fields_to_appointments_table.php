<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-side refusal motif + timestamp.
 *
 *   rejection_reason  — the text the owner typed when refusing. Shown to the
 *                       client on their rejected appointment card and to the
 *                       owner in the planning history.
 *   rejected_by_owner_at — when the refusal was recorded. Useful for audit
 *                          and to tell the free-slot flow apart (reject → free
 *                          becomes `cancelled`, but we keep this timestamp so
 *                          the original refusal is preserved).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('rejection_reason', 500)->nullable()->after('cancellation_reason');
            $table->timestamp('rejected_by_owner_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['rejection_reason', 'rejected_by_owner_at']);
        });
    }
};
