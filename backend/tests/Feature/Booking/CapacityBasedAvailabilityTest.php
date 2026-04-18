<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyBreak;
use App\Models\CompanyCapacityOverride;
use App\Models\CompanyDayOff;
use App\Models\CompanyOpeningHour;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class CapacityBasedAvailabilityTest extends TestCase
{
    private Company $company;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'         => 'Capacity Salon',
            'address'      => '1 Rue Test',
            'city'         => 'Paris',
            'booking_mode' => BookingMode::CapacityBased->value,
        ]);

        CompanyOpeningHour::create([
            'company_id'  => $this->company->id,
            'day_of_week' => 0, // Monday (enum)
            'open_time'   => '09:00:00',
            'close_time'  => '18:00:00',
            'is_closed'   => false,
        ]);

        $this->service = Service::create([
            'company_id'     => $this->company->id,
            'name'           => 'Coupe',
            'price'          => 30.00,
            'duration'       => 30,
            'is_active'      => true,
            'max_concurrent' => 3,
        ]);
    }

    private function getNextMonday(): Carbon
    {
        $date = Carbon::now()->addDays(1);
        while ($date->dayOfWeek !== Carbon::MONDAY) {
            $date->addDay();
        }
        return $date;
    }

    public function testSlotsReturnRemainingAndMax(): void
    {
        $monday = $this->getNextMonday();

        $response = $this->getJson(
            "/api/companies/{$this->company->id}/slots?date={$monday->format('Y-m-d')}&service_id={$this->service->id}"
        );

        $response->assertStatus(200);
        $slots = $response->json('data');

        $this->assertNotEmpty($slots);
        $firstSlot = $slots[0];
        $this->assertArrayHasKey('remaining', $firstSlot);
        $this->assertArrayHasKey('max', $firstSlot);
        $this->assertArrayHasKey('serviceId', $firstSlot);
        $this->assertEquals(3, $firstSlot['max']);
        $this->assertEquals(3, $firstSlot['remaining']);
        $this->assertTrue($firstSlot['available']);
    }

    public function testRemainingDecreasesWithBookings(): void
    {
        $monday   = $this->getNextMonday();
        $dateStr  = $monday->format('Y-m-d');
        $user     = User::factory()->create();

        // Book 2 slots at 09:00
        Appointment::create([
            'user_id'    => $user->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $dateStr,
            'start_time' => '09:00:00',
            'end_time'   => '09:30:00',
            'status'     => AppointmentStatus::Pending,
        ]);
        Appointment::create([
            'user_id'    => $user->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $dateStr,
            'start_time' => '09:00:00',
            'end_time'   => '09:30:00',
            'status'     => AppointmentStatus::Confirmed,
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->company->id}/slots?date={$dateStr}&service_id={$this->service->id}"
        );

        $slots = $response->json('data');
        $nineOClock = collect($slots)->first(fn ($s) => str_contains($s['dateTime'], 'T09:00:00'));

        $this->assertNotNull($nineOClock);
        $this->assertEquals(1, $nineOClock['remaining']);
        $this->assertTrue($nineOClock['available']);
    }

    public function testBreakBlocksSlot(): void
    {
        $monday  = $this->getNextMonday();
        $dateStr = $monday->format('Y-m-d');

        CompanyBreak::create([
            'company_id'  => $this->company->id,
            'day_of_week' => null, // every day
            'start_time'  => '12:00:00',
            'end_time'    => '13:00:00',
            'label'       => 'Lunch',
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->company->id}/slots?date={$dateStr}&service_id={$this->service->id}"
        );

        $slots      = $response->json('data');
        $noonSlot   = collect($slots)->first(fn ($s) => str_contains($s['dateTime'], 'T12:00:00'));

        $this->assertNotNull($noonSlot);
        $this->assertFalse($noonSlot['available']);
        $this->assertEquals(0, $noonSlot['remaining']);
    }

    public function testDayOffReturnsEmptySlots(): void
    {
        $monday  = $this->getNextMonday();
        $dateStr = $monday->format('Y-m-d');

        CompanyDayOff::create([
            'company_id' => $this->company->id,
            'date'       => $dateStr,
            'reason'     => 'Closed',
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->company->id}/slots?date={$dateStr}&service_id={$this->service->id}"
        );

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function testCapacityOverrideClampsMax(): void
    {
        $monday  = $this->getNextMonday();
        $dateStr = $monday->format('Y-m-d');

        CompanyCapacityOverride::create([
            'company_id' => $this->company->id,
            'date'       => $dateStr,
            'capacity'   => 1,
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->company->id}/slots?date={$dateStr}&service_id={$this->service->id}"
        );

        $slots = $response->json('data');
        $this->assertNotEmpty($slots);
        $this->assertEquals(1, $slots[0]['max']);
    }
}
