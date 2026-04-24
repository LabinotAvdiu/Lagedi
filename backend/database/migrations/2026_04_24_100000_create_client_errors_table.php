<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E28 — Erreurs client remontées depuis les apps Flutter.
 *
 * Capture les FlutterError, DioException, AsyncError et autres exceptions
 * levées côté app mobile/web. Permet à Labinot de débugger sans dépendre
 * uniquement de Firebase Crashlytics.
 *
 * Colonnes occurred_at  : timestamp client (fidèle même si l'upload est différé).
 * Colonne  received_at  : timestamp serveur — toujours fiable, défaut DB.
 *
 * Pas de timestamps() Eloquent — on gère received_at via DB default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_errors', function (Blueprint $table) {
            $table->id();

            // Nullable — l'erreur peut survenir avant que l'utilisateur soit connecté.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->string('platform', 16);       // android | ios | web
            $table->string('app_version', 32);     // ex: 1.0.0+2

            // Catégorie technique de l'erreur (FlutterError, DioException, …)
            $table->string('error_type', 64);

            // Message brut — tronqué côté Flutter à ~5000 chars avant envoi.
            $table->text('message');

            // Stack trace Dart — peut être longue (~10 000 chars).
            $table->text('stack_trace')->nullable();

            // Route GoRouter active au moment de l'erreur.
            $table->string('route', 255)->nullable();

            // Pour les DioException uniquement.
            $table->smallInteger('http_status')->nullable();
            $table->text('http_url')->nullable();

            // Métadonnées libres : appointmentId, locale, userId, etc.
            $table->json('context')->nullable();

            // Timestamp client — fiable même si l'upload arrive avec retard.
            $table->timestamp('occurred_at');

            // Timestamp serveur — défaut CURRENT_TIMESTAMP, jamais modifié.
            $table->timestamp('received_at')->useCurrent();

            // Index principaux pour les requêtes de debug.
            $table->index(['user_id', 'occurred_at'], 'ce_user_occurred');
            $table->index(['platform', 'app_version'], 'ce_platform_version');
            $table->index(['error_type'], 'ce_error_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_errors');
    }
};
