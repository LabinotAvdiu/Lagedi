<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\AppointmentStatus;
use App\Jobs\SendBookingConfirmationEmail;
use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingConfirmationMailTest extends TestCase
{
    private User        $client;
    private Company     $company;
    private Service     $service;
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role'   => 'user',
            'locale' => 'fr',
        ]);

        $owner = User::factory()->create(['role' => 'company']);

        $this->company = Company::create([
            'name'    => 'Salon Konfirmim',
            'address' => 'Rruga 15 Qershorit',
            'city'    => 'Prishtinë',
            'email'   => $owner->email,
            'gender'  => 'both',
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id'    => $owner->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);

        $category = ServiceCategory::create([
            'company_id' => $this->company->id,
            'name'       => 'Coiffure',
        ]);

        $this->service = Service::create([
            'company_id'  => $this->company->id,
            'category_id' => $category->id,
            'name'        => 'Coupe homme',
            'duration'    => 30,
            'price'       => 15.00,
            'is_active'   => true,
        ]);

        $this->appointment = Appointment::create([
            'user_id'    => $this->client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => now()->addDays(3)->toDateString(),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
        ]);
    }

    public function testJobIsQueued(): void
    {
        Queue::fake();

        SendBookingConfirmationEmail::dispatch($this->appointment);

        Queue::assertPushed(SendBookingConfirmationEmail::class);
    }

    public function testMailIsSentToClient(): void
    {
        Mail::fake();

        (new SendBookingConfirmationEmail($this->appointment))->handle();

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo($this->client->email);
        });
    }

    public function testMailHasIcsAttachment(): void
    {
        $mail = new BookingConfirmationMail($this->appointment);

        $attachments = $mail->attachments();

        $this->assertNotEmpty($attachments, 'BookingConfirmationMail must have at least one attachment.');
        // The attachment is a data-based Attachment (no ->as property accessible directly).
        // We verify via the built message that the ICS content is non-empty.
        $this->assertStringContainsString('BEGIN:VCALENDAR', $mail->icsContent);
    }

    public function testIcsContentIsValid(): void
    {
        $mail = new BookingConfirmationMail($this->appointment);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $mail->icsContent);
        $this->assertStringContainsString('BEGIN:VEVENT', $mail->icsContent);
        $this->assertStringContainsString($this->appointment->id . '@termini-im.com', $mail->icsContent);
        $this->assertStringContainsString('END:VEVENT', $mail->icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $mail->icsContent);
    }

    public function testMailSubjectFr(): void
    {
        Mail::fake();

        (new SendBookingConfirmationEmail($this->appointment))->handle();

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return str_contains($mail->envelope()->subject, 'Salon Konfirmim');
        });
    }

    public function testMailSubjectSq(): void
    {
        Mail::fake();

        $clientSq = User::factory()->create([
            'role'   => 'user',
            'locale' => 'sq',
        ]);

        $appt = Appointment::create([
            'user_id'    => $clientSq->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => now()->addDays(4)->toDateString(),
            'start_time' => '11:00:00',
            'end_time'   => '11:30:00',
            'status'     => AppointmentStatus::Confirmed,
        ]);

        (new SendBookingConfirmationEmail($appt))->handle();

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return str_contains($mail->envelope()->subject, '✓');
        });
    }

    public function testWalkInSkipsEmail(): void
    {
        Mail::fake();

        $walkIn = Appointment::create([
            'user_id'            => null,
            'company_id'         => $this->company->id,
            'service_id'         => $this->service->id,
            'date'               => now()->addDays(5)->toDateString(),
            'start_time'         => '14:00:00',
            'end_time'           => '14:30:00',
            'status'             => AppointmentStatus::Confirmed,
            'is_walk_in'         => true,
            'walk_in_first_name' => 'Arber',
        ]);

        (new SendBookingConfirmationEmail($walkIn))->handle();

        Mail::assertNothingSent();
    }
}
