<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D20 — Journal de toutes les notifications envoyées.
 *
 * Colonnes ref_type / ref_id : pointeur polymorphe optionnel vers la resource
 * concernée (appointment, review, walk_in…). Permettent le frequency cap (D23)
 * et le debug support sans ORM polymorphique lourd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->enum('channel', ['push', 'email', 'in-app']);
            $table->string('type', 64);

            // Payload complet envoyé (titre, body, data FCM ou variables email)
            $table->json('payload')->nullable();

            $table->timestamp('sent_at')->useCurrent();

            // Tracking engagement
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();

            // Resource de référence optionnelle (appointment, review, walk_in…)
            $table->string('ref_type', 64)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            // Index de requête fréquent (frequency cap + dashboard support)
            $table->index(['user_id', 'type', 'sent_at']);

            // Index pour les lookups par resource
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
