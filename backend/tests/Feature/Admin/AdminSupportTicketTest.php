<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SupportTicket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSupportTicketTest extends TestCase
{
    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    public function test_guest_cannot_access_admin_ticket_list(): void
    {
        $this->getJson('/api/admin/support-tickets')->assertStatus(401);
    }

    public function test_regular_user_is_forbidden_from_admin_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/support-tickets')->assertStatus(403);
    }

    public function test_admin_can_list_tickets_paginated(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        SupportTicket::query()->create([
            'first_name'  => 'Alice',
            'phone'       => '+38344000001',
            'email'       => 'alice@example.com',
            'message'     => 'Je n\'arrive pas a me connecter.',
            'source_page' => 'settings',
            'status'      => 'new',
            'attachments' => [
                [
                    'path' => 'support/abc-1/file-1.png',
                ],
            ],
        ]);

        SupportTicket::query()->create([
            'first_name'  => 'Bob',
            'phone'       => '+38344000002',
            'email'       => 'bob@example.com',
            'message'     => 'Le bouton ne fonctionne pas.',
            'source_page' => 'login',
            'status'      => 'resolved',
        ]);

        $response = $this->getJson('/api/admin/support-tickets?page=1&per_page=20');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'phone',
                        'email',
                        'message',
                        'source_page',
                        'status',
                        'attachment_urls',
                        'created_at',
                    ],
                ],
                'meta' => ['current_page', 'last_page'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.status', 'resolved')
            ->assertJsonPath('data.1.status', 'open');
    }

    public function test_per_page_param_is_respected(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        SupportTicket::query()->create([
            'first_name'  => 'A',
            'phone'       => '+1',
            'email'       => 'a@example.com',
            'message'     => 'Message valide numero un.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);
        SupportTicket::query()->create([
            'first_name'  => 'B',
            'phone'       => '+2',
            'email'       => 'b@example.com',
            'message'     => 'Message valide numero deux.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);

        $this->getJson('/api/admin/support-tickets?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_status_filter_returns_only_open_tickets(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        SupportTicket::query()->create([
            'first_name'  => 'Open',
            'phone'       => '+1',
            'email'       => 'open@example.com',
            'message'     => 'Ce ticket est ouvert.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);
        SupportTicket::query()->create([
            'first_name'  => 'Resolved',
            'phone'       => '+2',
            'email'       => 'resolved@example.com',
            'message'     => 'Ce ticket est resolu.',
            'source_page' => 'settings',
            'status'      => 'resolved',
        ]);

        $this->getJson('/api/admin/support-tickets?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'open');

        $this->getJson('/api/admin/support-tickets?status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'resolved');
    }

    public function test_admin_can_resolve_a_ticket(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $ticket = SupportTicket::query()->create([
            'first_name'  => 'Alice',
            'phone'       => '+38344000001',
            'email'       => 'alice@example.com',
            'message'     => 'Je n\'arrive pas a me connecter.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);

        $this->patchJson("/api/admin/support-tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertNoContent();

        $this->assertDatabaseHas('support_tickets', [
            'id'              => $ticket->id,
            'status'          => 'resolved',
            'resolved_by_id'  => $admin->id,
        ]);
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_resolving_twice_is_idempotent_and_preserves_original_resolver(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $ticket = SupportTicket::query()->create([
            'first_name'  => 'Alice',
            'phone'       => '+38344000001',
            'email'       => 'alice@example.com',
            'message'     => 'Je n\'arrive pas a me connecter.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);

        $this->patchJson("/api/admin/support-tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertNoContent();

        $resolvedAt = $ticket->fresh()->resolved_at;

        // Second call — must succeed silently and NOT overwrite audit fields.
        $this->patchJson("/api/admin/support-tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertNoContent();

        $this->assertEquals($resolvedAt, $ticket->fresh()->resolved_at);
        $this->assertDatabaseHas('support_tickets', [
            'id'             => $ticket->id,
            'resolved_by_id' => $admin->id,
        ]);
    }

    public function test_patch_with_invalid_status_returns_422(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $ticket = SupportTicket::query()->create([
            'first_name'  => 'Alice',
            'phone'       => '+38344000001',
            'email'       => 'alice@example.com',
            'message'     => 'Je n\'arrive pas a me connecter.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);

        $this->patchJson("/api/admin/support-tickets/{$ticket->id}", [
            'status' => 'open',
        ])->assertStatus(422);
    }
}
