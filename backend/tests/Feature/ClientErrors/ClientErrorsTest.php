<?php

declare(strict_types=1);

namespace Tests\Feature\ClientErrors;

use App\Models\ClientError;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E28 — Tests du pipeline d'error reporting Flutter → Laravel.
 */
class ClientErrorsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validError(array $overrides = []): array
    {
        return array_merge([
            'platform'    => 'android',
            'app_version' => '1.0.0+2',
            'error_type'  => 'FlutterError',
            'message'     => 'Test error message',
            'occurred_at' => now()->toIso8601String(),
        ], $overrides);
    }

    private function makeOwner(): User
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create();
        CompanyUser::create([
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // POST /api/errors — store
    // -------------------------------------------------------------------------

    public function test_store_accepts_batch_up_to_50(): void
    {
        $errors = array_fill(0, 50, $this->validError());

        $response = $this->postJson('/api/errors', ['errors' => $errors]);

        $response->assertNoContent();
        $this->assertDatabaseCount('client_errors', 50);
    }

    public function test_store_attaches_user_id_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/errors', ['errors' => [$this->validError()]]);

        $response->assertNoContent();
        $this->assertDatabaseHas('client_errors', ['user_id' => $user->id]);
    }

    public function test_store_works_without_auth_for_unauthenticated_crash(): void
    {
        $response = $this->postJson('/api/errors', [
            'errors' => [$this->validError(['error_type' => 'AsyncError'])],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('client_errors', [
            'user_id'    => null,
            'error_type' => 'AsyncError',
        ]);
    }

    public function test_store_rejects_batch_over_50(): void
    {
        $errors = array_fill(0, 51, $this->validError());

        $response = $this->postJson('/api/errors', ['errors' => $errors]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['errors']);
        $this->assertDatabaseCount('client_errors', 0);
    }

    public function test_store_validates_platform_enum(): void
    {
        $response = $this->postJson('/api/errors', [
            'errors' => [$this->validError(['platform' => 'windows'])],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['errors.0.platform']);
    }

    public function test_store_persists_dio_exception_fields(): void
    {
        $error = $this->validError([
            'error_type'  => 'DioException',
            'http_status' => 422,
            'http_url'    => 'https://api.termini.im/api/bookings',
            'stack_trace' => '#0 main.dart:10',
            'route'       => '/booking',
            'context'     => ['appointmentId' => 99],
        ]);

        $this->postJson('/api/errors', ['errors' => [$error]])->assertNoContent();

        $this->assertDatabaseHas('client_errors', [
            'error_type'  => 'DioException',
            'http_status' => 422,
            'route'       => '/booking',
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/errors — index
    // -------------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->getJson('/api/errors');

        $response->assertUnauthorized();
    }

    public function test_index_returns_recent_errors_for_owner(): void
    {
        $owner = $this->makeOwner();

        // Crée 3 erreurs en base.
        ClientError::create($this->validError() + ['occurred_at' => now()]);
        ClientError::create($this->validError(['platform' => 'ios']) + ['occurred_at' => now()]);
        ClientError::create($this->validError(['platform' => 'web']) + ['occurred_at' => now()]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/errors');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_platform(): void
    {
        $owner = $this->makeOwner();

        ClientError::create($this->validError(['platform' => 'android']) + ['occurred_at' => now()]);
        ClientError::create($this->validError(['platform' => 'ios']) + ['occurred_at' => now()]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/errors?platform=android');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.platform', 'android');
    }

    public function test_index_forbidden_for_non_owner(): void
    {
        $user = User::factory()->create(); // pas owner

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/errors');

        $response->assertForbidden();
    }
}
