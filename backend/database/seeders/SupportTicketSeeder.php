<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    /**
     * Seed a handful of realistic support tickets for local dev so the
     * admin side has something to look at without writing SQL.
     *
     * Covers the full spectrum:
     *  - guest ticket (no user_id)
     *  - client ticket
     *  - owner ticket
     *  - ticket with source_context (companyId from fiche salon)
     *  - tickets at every status stage
     */
    public function run(): void
    {
        // Wipe any previous seed run so re-running doesn't keep stacking
        // duplicate rows in local dev.
        SupportTicket::query()->delete();

        // Pick any existing users if available — match by role so the
        // seeder stays standalone whatever other seeders ran first.
        $client = User::where('role', 'user')->first()
               ?? User::where('role', null)->first()
               ?? User::first();
        $owner  = User::where('role', 'company')->first();

        $now = now();

        SupportTicket::create([
            'user_id'        => null,
            'first_name'     => 'Jean',
            'phone'          => '+33 6 12 34 56 78',
            'email'          => 'jean.durand@example.com',
            'message'        => "Bonjour, j'ai essayé de créer un compte mais je ne reçois pas l'email de vérification. Pouvez-vous vérifier ?",
            'attachments'    => null,
            'source_page'    => 'login',
            'source_context' => ['locale' => 'fr', 'platform' => 'web'],
            'status'         => 'new',
            'created_at'     => $now->copy()->subHours(2),
            'updated_at'     => $now->copy()->subHours(2),
        ]);

        if ($client !== null) {
            SupportTicket::create([
                'user_id'        => $client->id,
                'first_name'     => $client->first_name,
                'phone'          => $client->phone ?? '+383 44 123 456',
                'email'          => $client->email,
                'message'        => "Je ne trouve pas de salon pour femmes dans ma ville. Est-ce normal ?",
                'attachments'    => null,
                'source_page'    => 'settings',
                'source_context' => ['locale' => 'fr', 'platform' => 'android'],
                'status'         => 'in_progress',
                'admin_notes'    => "Confirmer avec Donjeta si elle active bien la visibilité femmes.",
                'created_at'     => $now->copy()->subDays(1),
                'updated_at'     => $now->copy()->subHours(6),
            ]);

            SupportTicket::create([
                'user_id'        => $client->id,
                'first_name'     => $client->first_name,
                'phone'          => $client->phone ?? '+383 44 123 456',
                'email'          => $client->email,
                'message'        => "Le salon Donjeta est super ! Juste les photos de la galerie ne chargent pas bien sur mon téléphone.",
                'attachments'    => null,
                'source_page'    => 'company_detail',
                'source_context' => [
                    'companyId' => '5',
                    'locale'    => 'fr',
                    'platform'  => 'ios',
                ],
                'status'         => 'resolved',
                'admin_notes'    => "Problème de cache cloud — corrigé en v1.1.2.",
                'created_at'     => $now->copy()->subDays(4),
                'updated_at'     => $now->copy()->subDays(2),
            ]);
        }

        if ($owner !== null) {
            SupportTicket::create([
                'user_id'        => $owner->id,
                'first_name'     => $owner->first_name,
                'phone'          => $owner->phone ?? '+383 49 987 654',
                'email'          => $owner->email,
                'message'        => "Un de mes employés n'arrive pas à se connecter. Pouvez-vous regarder son compte ?",
                'attachments'    => null,
                'source_page'    => 'my_company',
                'source_context' => ['locale' => 'sq', 'platform' => 'android'],
                'status'         => 'new',
                'created_at'     => $now->copy()->subHours(5),
                'updated_at'     => $now->copy()->subHours(5),
            ]);

            SupportTicket::create([
                'user_id'        => $owner->id,
                'first_name'     => $owner->first_name,
                'phone'          => $owner->phone ?? '+383 49 987 654',
                'email'          => $owner->email,
                'message'        => "Bonjour, est-il possible d'ajouter un mode de paiement en ligne ?",
                'attachments'    => null,
                'source_page'    => 'desktop_menu',
                'source_context' => ['locale' => 'fr', 'platform' => 'web'],
                'status'         => 'archived',
                'admin_notes'    => "Roadmap Q3 — garder comme demande utilisateur.",
                'created_at'     => $now->copy()->subWeeks(2),
                'updated_at'     => $now->copy()->subWeeks(1),
            ]);
        }
    }
}
