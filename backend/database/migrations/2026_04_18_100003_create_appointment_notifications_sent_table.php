<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_notifications_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->timestamp('sent_at');

            // Garantit qu'un même (notification, appt, user) ne part qu'une fois.
            // Le nom est raccourci car MySQL limite les identifiants à 64 caractères.
            $table->unique(['appointment_id', 'user_id', 'type'], 'appt_notif_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notifications_sent');
    }
};
