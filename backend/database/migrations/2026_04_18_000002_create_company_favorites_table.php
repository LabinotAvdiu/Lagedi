<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_favorites', function (Blueprint $table): void {
            // Composite primary key — prevents duplicate rows at the DB level.
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');

            // created_at only — used to sort favorites oldest-first in the home listing.
            // updated_at is intentionally omitted: a favorite either exists or it doesn't.
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys with cascading deletes so orphaned rows can never accumulate.
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->foreign('company_id')
                  ->references('id')->on('companies')
                  ->onDelete('cascade');

            // Composite PK — the DB itself enforces idempotence.
            $table->primary(['user_id', 'company_id']);

            // Index on (user_id, created_at) to efficiently fetch a user's favorites
            // ordered by creation date without a filesort.
            $table->index(['user_id', 'created_at'], 'favorites_user_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_favorites');
    }
};
