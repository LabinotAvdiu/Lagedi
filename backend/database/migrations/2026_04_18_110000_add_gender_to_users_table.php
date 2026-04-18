<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal gender on the user account.
 *
 *  - 'men' / 'women' → clients pick one at signup and it drives the default
 *    gender filter on the home list. Binary-only; the home filter's "both"
 *    option is a list-level concept (show all salons), not a user attribute.
 *  - null            → "prefer not to say" / not collected. Home filter
 *    falls back to 'both' (show everything).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('gender', ['men', 'women'])
                ->nullable()
                ->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('gender');
        });
    }
};
