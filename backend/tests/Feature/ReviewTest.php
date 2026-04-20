<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCompanyWithOwner(): array
    {
        $owner = User::factory()->create(['role' => 'company']);

        $company = Company::create([
            'name'         => 'Test Salon',
            'address'      => '1 Rue Test',
            'city'         => 'Paris',
            'booking_mode' => BookingMode::CapacityBased->value,
            'rating'       => 0,
            'review_count' => 0,
        ]);

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'name'       => 'Coupe',
            'price'      => 25.0,
            'duration'   => 30,
            'is_active'  => true,
        ]);

        return compact('owner', 'company', 'service');
    }

    /** Crée un appointment passé (confirmed, hier) avec le client donné. */
    private function makePastAppointment(User $client, Company $company, Service $service, int $hoursAgo = 2): Appointment
    {
        return Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->subHours($hoursAgo)->format('Y-m-d'),
            'start_time' => now()->subHours($hoursAgo)->format('H:i:s'),
            'end_time'   => now()->subHours($hoursAgo - 1)->format('H:i:s'),
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);
    }

    // =========================================================================
    // Test 1 — Client peut poster une review pour un RDV passé confirmé
    // =========================================================================

    public function test_client_can_post_review_for_past_confirmed_appointment(): void
    {
        ['company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $client      = User::factory()->create();
        $appointment = $this->makePastAppointment($client, $company, $service);

        Sanctum::actingAs($client);

        $response = $this->postJson("/api/appointments/{$appointment->id}/review", [
            'rating'  => 4,
            'comment' => 'Super salon !',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Super salon !');

        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $appointment->id,
            'user_id'        => $client->id,
            'company_id'     => $company->id,
            'rating'         => 4,
            'status'         => 'visible',
        ]);

        // Rating company doit être mis à jour
        $company->refresh();
        $this->assertEquals(4, (int) round((float) $company->rating));
        $this->assertEquals(1, $company->review_count);
    }

    // =========================================================================
    // Test 2 — Rejet si RDV pas encore passé (futur)
    // =========================================================================

    public function test_review_rejected_if_appointment_not_yet_past(): void
    {
        ['company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $client = User::factory()->create();

        // RDV futur
        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/appointments/{$appointment->id}/review", [
            'rating' => 5,
        ])->assertStatus(422)
          ->assertJsonPath('errors.appointment.0', 'not-reviewable-status');
    }

    // =========================================================================
    // Test 3 — Rejet si > 30 jours
    // =========================================================================

    public function test_review_rejected_if_outside_30_day_window(): void
    {
        ['company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $client = User::factory()->create();

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->subDays(31)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/appointments/{$appointment->id}/review", [
            'rating' => 3,
        ])->assertStatus(422)
          ->assertJsonPath('errors.appointment.0', 'review-window-expired');
    }

    // =========================================================================
    // Test 4 — Rejet si déjà reviewé (unique par appointment)
    // =========================================================================

    public function test_review_rejected_if_already_reviewed(): void
    {
        ['company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $client      = User::factory()->create();
        $appointment = $this->makePastAppointment($client, $company, $service);

        // Créer une première review
        Review::create([
            'appointment_id' => $appointment->id,
            'user_id'        => $client->id,
            'company_id'     => $company->id,
            'rating'         => 5,
            'status'         => 'visible',
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/appointments/{$appointment->id}/review", [
            'rating' => 3,
        ])->assertStatus(422)
          ->assertJsonPath('errors.appointment.0', 'already-reviewed');
    }

    // =========================================================================
    // Test 5 — Owner peut hide/unhide → rating recalculé
    // =========================================================================

    public function test_owner_can_hide_and_unhide_review_and_rating_recalculates(): void
    {
        ['owner' => $owner, 'company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $clientA = User::factory()->create();
        $clientB = User::factory()->create();

        $apptA = $this->makePastAppointment($clientA, $company, $service);
        $apptB = $this->makePastAppointment($clientB, $company, $service);

        $reviewA = Review::create([
            'appointment_id' => $apptA->id,
            'user_id'        => $clientA->id,
            'company_id'     => $company->id,
            'rating'         => 5,
            'status'         => 'visible',
        ]);

        Review::create([
            'appointment_id' => $apptB->id,
            'user_id'        => $clientB->id,
            'company_id'     => $company->id,
            'rating'         => 3,
            'status'         => 'visible',
        ]);

        // Forcer le rating initial = (5+3)/2 = 4
        $company->update(['rating' => 4.00, 'review_count' => 2]);

        Sanctum::actingAs($owner);

        // Hide la review A (rating 5)
        $this->putJson("/api/my-company/reviews/{$reviewA->id}/hide", [
            'reason' => 'Spam',
        ])->assertOk()
          ->assertJsonPath('data.status', 'hidden_by_owner');

        $company->refresh();
        $this->assertEquals(1, $company->review_count);
        $this->assertEquals(3, (int) round((float) $company->rating));

        // Unhide — le rating revient à 4
        $this->putJson("/api/my-company/reviews/{$reviewA->id}/unhide")
            ->assertOk()
            ->assertJsonPath('data.status', 'visible');

        $company->refresh();
        $this->assertEquals(2, $company->review_count);
        $this->assertEquals(4, (int) round((float) $company->rating));
    }

    // =========================================================================
    // Test 6 — Anonyme → 401 sur POST, 200 sur GET public
    // =========================================================================

    public function test_anonymous_gets_401_on_post_and_200_on_public_get(): void
    {
        ['company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $client = User::factory()->create();
        $appt   = $this->makePastAppointment($client, $company, $service);

        // 401 sur POST sans auth
        $this->postJson("/api/appointments/{$appt->id}/review", ['rating' => 4])
            ->assertStatus(401);

        // 200 sur GET public des reviews de la company (aucune review → liste vide)
        $this->getJson("/api/companies/{$company->id}/reviews")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    // =========================================================================
    // Test 7 — Liste publique filtre hidden_by_owner
    // =========================================================================

    public function test_public_reviews_list_filters_hidden_reviews(): void
    {
        ['company' => $company, 'service' => $service] = $this->makeCompanyWithOwner();

        $clientA = User::factory()->create();
        $clientB = User::factory()->create();

        $apptA = $this->makePastAppointment($clientA, $company, $service);
        $apptB = $this->makePastAppointment($clientB, $company, $service);

        // Review visible
        Review::create([
            'appointment_id' => $apptA->id,
            'user_id'        => $clientA->id,
            'company_id'     => $company->id,
            'rating'         => 5,
            'status'         => 'visible',
        ]);

        // Review masquée par owner
        Review::create([
            'appointment_id' => $apptB->id,
            'user_id'        => $clientB->id,
            'company_id'     => $company->id,
            'rating'         => 1,
            'status'         => 'hidden_by_owner',
        ]);

        $response = $this->getJson("/api/companies/{$company->id}/reviews");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(5, $data[0]['rating']);
    }
}
