<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D19 — Table de préférences de notifications granulaires par canal × type.
 *
 * Les types TRANSACTIONNELS ne sont PAS stockés ici — ils sont toujours envoyés.
 * Seuls les types configurables (voir NotificationType::all()) ont des lignes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // push | email | in-app
            $table->enum('channel', ['push', 'email', 'in-app']);

            // e.g. reminder_evening, marketing, new_review…
            $table->string('type', 64);

            $table->boolean('enabled')->default(true);

            $table->timestamps();

            // Un utilisateur ne peut avoir qu'une préférence par (channel, type)
            $table->unique(['user_id', 'channel', 'type']);

            // Index composite pour les lookups fréquents
            $table->index(['user_id', 'channel', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
