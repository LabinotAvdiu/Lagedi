<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two pre-launch waitlists: clients and salon owners.
 *
 * Visible fields are minimal (4-6 inputs) so we don't kill mobile conversion.
 * Auto-derived fields (locale, utm_*, ip_country, is_diaspora, unsubscribe_token)
 * give us all the segmentation + RGPD compliance levers without asking the user
 * for anything extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_waitlist', function (Blueprint $table) {
            $table->id();

            // Visible inputs
            $table->string('name', 80);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 50);
            $table->string('source', 30); // facebook | instagram | tiktok | other

            // Consent (required to insert — captured at submit time)
            $table->timestamp('cgu_accepted_at');

            // Server-derived (no user input)
            $table->string('locale', 5)->nullable();          // sq | fr | en
            $table->string('ip_country', 2)->nullable();       // ISO 2-letter
            $table->boolean('is_diaspora')->default(false);
            $table->string('utm_source', 64)->nullable();
            $table->string('utm_medium', 64)->nullable();
            $table->string('utm_campaign', 64)->nullable();
            $table->string('referrer_url', 500)->nullable();

            // 1-click unsubscribe (RGPD)
            $table->string('unsubscribe_token', 64)->unique();

            // Pipeline state
            $table->enum('status', ['new', 'contacted', 'converted', 'declined'])
                ->default('new');

            $table->timestamps();

            $table->index('city');
            $table->index('source');
            $table->index('status');
            $table->index('is_diaspora');
        });

        // CHECK : at least one contact channel
        DB::statement('ALTER TABLE client_waitlist ADD CONSTRAINT chk_client_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)');

        Schema::create('owner_waitlist', function (Blueprint $table) {
            $table->id();

            // Visible inputs
            $table->string('owner_name', 80);
            $table->string('salon_name', 120);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 50);
            $table->string('source', 30);
            $table->enum('when_to_start', ['now', 'at_launch']); // pilot intent signal

            $table->timestamp('cgu_accepted_at');

            // Server-derived
            $table->string('locale', 5)->nullable();
            $table->string('ip_country', 2)->nullable();
            $table->boolean('is_diaspora')->default(false);
            $table->string('utm_source', 64)->nullable();
            $table->string('utm_medium', 64)->nullable();
            $table->string('utm_campaign', 64)->nullable();
            $table->string('referrer_url', 500)->nullable();

            $table->string('unsubscribe_token', 64)->unique();

            $table->enum('status', ['new', 'contacted', 'pilot', 'converted', 'declined'])
                ->default('new');

            $table->timestamps();

            $table->index('city');
            $table->index('source');
            $table->index('status');
            $table->index('when_to_start');
        });

        DB::statement('ALTER TABLE owner_waitlist ADD CONSTRAINT chk_owner_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('client_waitlist');
        Schema::dropIfExists('owner_waitlist');
    }
};
