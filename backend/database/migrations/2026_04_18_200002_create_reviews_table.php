<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned();  // 1–5
            $table->text('comment')->nullable();
            $table->enum('status', ['visible', 'hidden_by_owner', 'removed_by_admin'])->default('visible');
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedBigInteger('hidden_by')->nullable();
            $table->foreign('hidden_by')->references('id')->on('users')->nullOnDelete();
            $table->string('moderation_note', 500)->nullable();
            $table->timestamps();

            // Index pour la liste publique paginée (company + status + date desc)
            $table->index(['company_id', 'status', 'created_at'], 'reviews_company_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
