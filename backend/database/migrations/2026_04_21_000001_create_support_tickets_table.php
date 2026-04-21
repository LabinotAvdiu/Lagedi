<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name', 100);
            $table->string('phone', 50);
            $table->string('email', 255)->nullable();
            $table->text('message');

            // Array of {path, original_name, mime, size_bytes}. Max 3 entries.
            $table->json('attachments')->nullable();

            // Which screen the user was on: settings, my_company, company_detail,
            // login, signup, desktop_menu
            $table->string('source_page', 40);
            // Extra context: {companyId?: string, locale?: string, platform?: string, appVersion?: string}
            $table->json('source_context')->nullable();

            // new | in_progress | resolved | archived
            $table->string('status', 20)->default('new');
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('source_page');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
