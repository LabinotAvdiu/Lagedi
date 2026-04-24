<?php

declare(strict_types=1);

namespace Tests\Feature\Waitlist;

use App\Models\ClientWaitlistEntry;
use App\Models\OwnerWaitlistEntry;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    // -------------------------------------------------------------------------
    // CLIENT
    // -------------------------------------------------------------------------

    public function testClientWaitlistAcceptsEmailContact(): void
    {
        $this->postJson('/api/waitlist/client', [
            'name'         => 'Albjon',
            'contact'      => 'albjon@example.com',
            'city'         => 'Ferizaj',
            'source'       => 'instagram',
            'cgu_accepted' => true,
        ])->assertStatus(201)
          ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('client_waitlist', [
            'name'   => 'Albjon',
            'email'  => 'albjon@example.com',
            'phone'  => null,
            'city'   => 'Ferizaj',
            'source' => 'instagram',
            'status' => 'new',
        ]);

        $row = ClientWaitlistEntry::first();
        $this->assertNotEmpty($row->unsubscribe_token);
        $this->assertNotNull($row->cgu_accepted_at);
    }

    public function testClientWaitlistAcceptsPhoneContact(): void
    {
        $this->postJson('/api/waitlist/client', [
            'name'         => 'Lirie',
            'contact'      => '+38349123456',
            'city'         => 'Prishtinë',
            'source'       => 'tiktok',
            'cgu_accepted' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('client_waitlist', [
            'phone' => '+38349123456',
            'email' => null,
        ]);
    }

    public function testClientWaitlistNormalisesPhoneSpaces(): void
    {
        $this->postJson('/api/waitlist/client', [
            'name'         => 'Test',
            'contact'      => '+383 49 123 456',
            'city'         => 'Pejë',
            'source'       => 'facebook',
            'cgu_accepted' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('client_waitlist', ['phone' => '+38349123456']);
    }

    public function testClientWaitlistRejectsInvalidContact(): void
    {
        $this->postJson('/api/waitlist/client', [
            'name'         => 'Albjon',
            'contact'      => 'notacontact',
            'city'         => 'Ferizaj',
            'source'       => 'other',
            'cgu_accepted' => true,
        ])->assertStatus(422)
          ->assertJsonPath('errors.contact.0', 'invalid_format');
    }

    public function testClientWaitlistRequiresCguAcceptance(): void
    {
        $this->postJson('/api/waitlist/client', [
            'name'         => 'Test',
            'contact'      => 'a@b.com',
            'city'         => 'Ferizaj',
            'source'       => 'other',
            // cgu_accepted missing
        ])->assertStatus(422);
    }

    public function testClientWaitlistRejectsUnknownCity(): void
    {
        $this->postJson('/api/waitlist/client', [
            'name'         => 'Test',
            'contact'      => 'a@b.com',
            'city'         => 'Paris',  // not in CITIES
            'source'       => 'instagram',
            'cgu_accepted' => true,
        ])->assertStatus(422);
    }

    public function testClientWaitlistDetectsDiasporaFromCfHeader(): void
    {
        $this->withHeaders([
            'CF-IPCountry'    => 'CH',
            'Accept-Language' => 'sq-AL,sq;q=0.9',
        ])->postJson('/api/waitlist/client', [
            'name'         => 'Diaspora',
            'contact'      => 'd@example.com',
            'city'         => 'Tjetër',
            'source'       => 'facebook',
            'cgu_accepted' => true,
        ])->assertStatus(201);

        $row = ClientWaitlistEntry::first();
        $this->assertSame('CH', $row->ip_country);
        $this->assertTrue($row->is_diaspora);
        $this->assertSame('sq', $row->locale);
    }

    public function testClientWaitlistKosovoIpIsNotDiaspora(): void
    {
        $this->withHeaders(['CF-IPCountry' => 'XK'])
            ->postJson('/api/waitlist/client', [
                'name'         => 'Local',
                'contact'      => 'l@example.com',
                'city'         => 'Ferizaj',
                'source'       => 'instagram',
                'cgu_accepted' => true,
            ])->assertStatus(201);

        $row = ClientWaitlistEntry::first();
        $this->assertSame('XK', $row->ip_country);
        $this->assertFalse($row->is_diaspora);
    }

    public function testClientWaitlistCapturesUtmFromQuery(): void
    {
        $this->postJson('/api/waitlist/client?utm_source=ig&utm_medium=story&utm_campaign=launch', [
            'name'         => 'UTM',
            'contact'      => 'utm@example.com',
            'city'         => 'Ferizaj',
            'source'       => 'instagram',
            'cgu_accepted' => true,
        ])->assertStatus(201);

        $row = ClientWaitlistEntry::first();
        $this->assertSame('ig', $row->utm_source);
        $this->assertSame('story', $row->utm_medium);
        $this->assertSame('launch', $row->utm_campaign);
    }

    // -------------------------------------------------------------------------
    // OWNER
    // -------------------------------------------------------------------------

    public function testOwnerWaitlistAcceptsValidPayload(): void
    {
        $this->postJson('/api/waitlist/owner', [
            'owner_name'    => 'Lirie',
            'salon_name'    => 'Studio L',
            'contact'       => 'studio@example.com',
            'city'          => 'Ferizaj',
            'source'        => 'instagram',
            'when_to_start' => 'now',
            'cgu_accepted'  => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('owner_waitlist', [
            'owner_name'    => 'Lirie',
            'salon_name'    => 'Studio L',
            'email'         => 'studio@example.com',
            'when_to_start' => 'now',
        ]);
    }

    public function testOwnerWaitlistRejectsInvalidWhenToStart(): void
    {
        $this->postJson('/api/waitlist/owner', [
            'owner_name'    => 'X',
            'salon_name'    => 'Y',
            'contact'       => 'x@y.com',
            'city'          => 'Ferizaj',
            'source'        => 'tiktok',
            'when_to_start' => 'maybe',
            'cgu_accepted'  => true,
        ])->assertStatus(422);
    }

    public function testOwnerWaitlistRequiresAllFields(): void
    {
        $this->postJson('/api/waitlist/owner', [
            'owner_name' => 'Only',
            // salon_name, contact, city, source, when_to_start missing
        ])->assertStatus(422);
    }
}
