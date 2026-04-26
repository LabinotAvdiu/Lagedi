<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_favorites', function (Blueprint $table): void {
            // Optional preferred employee for this favorite. When set, the
            // client app shows a dual-entry pattern in favorites: one card
            // with the employee preselected (booking locked to that pro)
            // and one plain card for free employee selection.
            //
            // Only meaningful when company.booking_mode = 'employee_based'.
            // For capacity_based salons the column is ignored client-side.
            $table->unsignedBigInteger('preferred_employee_id')
                  ->nullable()
                  ->after('company_id');

            $table->foreign('preferred_employee_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('company_favorites', function (Blueprint $table): void {
            $table->dropForeign(['preferred_employee_id']);
            $table->dropColumn('preferred_employee_id');
        });
    }
};
