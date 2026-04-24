<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('invited_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email', 255);              // always lower()
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->json('specialties');                 // default '[]' set at insert time
            $table->string('role', 32)->default('employee');

            $table->char('token_hash', 64);              // sha256 hex
            $table->string('status', 16)->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('refused_at')->nullable();

            $table->foreignId('resulting_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('token_hash', 'idx_invitations_token_hash');
            $table->index(['email', 'status'], 'idx_invitations_email_status');
            $table->index(['company_id', 'email', 'status'], 'idx_invitations_company_email_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_invitations');
    }
};
