<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyGalleryImage;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Type2CompanySeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // Capacity-based salon in Prishtina
        // ─────────────────────────────────────────────────────────────────────

        // Prishtina centre coordinates
        $lat = 42.6629;
        $lng = 21.1655;

        $company = Company::create([
            'name'              => 'Salloni Donjeta',
            'description'       => 'Sallon familjar në zemër të Prishtinës. Kapacitet i menaxhuar nga vetë pronari: tri karrige, tre prerje në të njëjtën kohë.',
            'phone'             => '+383 44 123 456',
            'email'             => 'salloni.donjeta@termini.im',
            'address'           => 'Rruga Nënë Tereza 12',
            'city'              => 'Prishtinë',
            'postal_code'       => '10000',
            'country'           => 'Kosovë',
            'gender'            => 'both',
            'profile_image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80',
            'rating'            => 4.8,
            'review_count'      => 142,
            'price_level'       => 2,
            'booking_mode'      => BookingMode::CapacityBased->value,
        ]);

        DB::statement(
            'UPDATE companies SET location = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
            [$lat, $lng, $company->id]
        );

        CompanyGalleryImage::create([
            'company_id' => $company->id,
            'image_path' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80',
            'sort_order' => 0,
        ]);
        CompanyGalleryImage::create([
            'company_id' => $company->id,
            'image_path' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800&q=80',
            'sort_order' => 1,
        ]);

        // Opening hours — closed Sunday (6 = Sunday in the enum)
        for ($day = 0; $day < 7; $day++) {
            CompanyOpeningHour::create([
                'company_id'  => $company->id,
                'day_of_week' => $day,
                'open_time'   => $day === 6 ? null : '09:00',
                'close_time'  => $day === 6 ? null : '19:00',
                'is_closed'   => $day === 6,
            ]);
        }

        // Owner
        $owner = User::create([
            'first_name' => 'Donjeta',
            'last_name'  => 'Krasniqi',
            'email'      => 'donjeta@termini.im',
            'password'   => Hash::make('Password1'),
            'phone'      => '+383 44 123 456',
            'city'       => 'Prishtinë',
            'role'       => UserRole::Company,
            'locale'     => 'sq',
        ]);

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // Categories + services (with max_concurrent)
        // ─────────────────────────────────────────────────────────────────────
        $catPrerje  = ServiceCategory::create(['company_id' => $company->id, 'name' => 'Prerje']);
        $catNgjyra  = ServiceCategory::create(['company_id' => $company->id, 'name' => 'Ngjyra']);
        $catMirembaj = ServiceCategory::create(['company_id' => $company->id, 'name' => 'Mirëmbajtje']);

        $svcPrerjeSignature = Service::create([
            'company_id'     => $company->id,
            'category_id'    => $catPrerje->id,
            'name'           => 'Prerje signature',
            'description'    => 'Prerje e plotë me gërshërë ose makinë, shampo e përfshirë.',
            'price'          => 15.00,
            'duration'       => 30,
            'gender'         => 'both',
            'max_concurrent' => 3,
            'is_active'      => true,
        ]);

        $svcNgjyrosje = Service::create([
            'company_id'     => $company->id,
            'category_id'    => $catNgjyra->id,
            'name'           => 'Ngjyrosje',
            'description'    => 'Ngjyrim i plotë me produkt profesional.',
            'price'          => 45.00,
            'duration'       => 90,
            'gender'         => 'women',
            'max_concurrent' => 2,
            'is_active'      => true,
        ]);

        $svcBrashim = Service::create([
            'company_id'     => $company->id,
            'category_id'    => $catMirembaj->id,
            'name'           => 'Brashim',
            'description'    => 'Tharje + modelim me furçë.',
            'price'          => 12.00,
            'duration'       => 20,
            'gender'         => 'both',
            'max_concurrent' => 3,
            'is_active'      => true,
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // Appointments — mix of confirmed + pending on next 3 days
        // ─────────────────────────────────────────────────────────────────────
        $base = Carbon::today();
        $samples = [
            // Today — mostly confirmed
            ['offset' => 0, 'start' => '10:00', 'duration' => 30, 'status' => AppointmentStatus::Confirmed, 'service' => $svcPrerjeSignature, 'first' => 'Arben', 'last' => 'Hoxha',    'phone' => '+383 44 200 001'],
            ['offset' => 0, 'start' => '10:30', 'duration' => 30, 'status' => AppointmentStatus::Confirmed, 'service' => $svcPrerjeSignature, 'first' => 'Lirim',  'last' => 'Bytyqi',   'phone' => '+383 44 200 002'],
            ['offset' => 0, 'start' => '11:30', 'duration' => 90, 'status' => AppointmentStatus::Pending,   'service' => $svcNgjyrosje,       'first' => 'Shqipe', 'last' => 'Dervishaj','phone' => '+383 44 200 003'],
            ['offset' => 0, 'start' => '14:00', 'duration' => 20, 'status' => AppointmentStatus::Confirmed, 'service' => $svcBrashim,         'first' => 'Dea',    'last' => 'Morina',   'phone' => '+383 44 200 004'],
            ['offset' => 0, 'start' => '14:20', 'duration' => 20, 'status' => AppointmentStatus::Confirmed, 'service' => $svcBrashim,         'first' => 'Besa',   'last' => 'Gashi',    'phone' => '+383 44 200 005'],

            // Tomorrow
            ['offset' => 1, 'start' => '09:30', 'duration' => 30, 'status' => AppointmentStatus::Pending,   'service' => $svcPrerjeSignature, 'first' => 'Besart', 'last' => 'Krasniqi', 'phone' => '+383 44 200 006'],
            ['offset' => 1, 'start' => '10:00', 'duration' => 30, 'status' => AppointmentStatus::Confirmed, 'service' => $svcPrerjeSignature, 'first' => 'Ardian', 'last' => 'Haxhiu',   'phone' => '+383 44 200 007'],
            ['offset' => 1, 'start' => '13:00', 'duration' => 90, 'status' => AppointmentStatus::Pending,   'service' => $svcNgjyrosje,       'first' => 'Vlora',  'last' => 'Shala',    'phone' => '+383 44 200 008'],

            // Day after tomorrow
            ['offset' => 2, 'start' => '11:00', 'duration' => 30, 'status' => AppointmentStatus::Confirmed, 'service' => $svcPrerjeSignature, 'first' => 'Gent',   'last' => 'Berisha',  'phone' => '+383 44 200 009'],
            ['offset' => 2, 'start' => '15:00', 'duration' => 20, 'status' => AppointmentStatus::Pending,   'service' => $svcBrashim,         'first' => 'Albina', 'last' => 'Hajdini',  'phone' => '+383 44 200 010'],
        ];

        foreach ($samples as $s) {
            $date = $base->copy()->addDays($s['offset']);
            $start = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $s['start']);
            $end   = $start->copy()->addMinutes($s['duration']);
            Appointment::create([
                'company_id'         => $company->id,
                'company_user_id'    => null,
                'user_id'            => null,
                'service_id'         => $s['service']->id,
                'date'               => $date->format('Y-m-d'),
                'start_time'         => $start->format('H:i:s'),
                'end_time'           => $end->format('H:i:s'),
                'status'             => $s['status'],
                'is_walk_in'         => true,
                'walk_in_first_name' => $s['first'],
                'walk_in_last_name'  => $s['last'],
                'walk_in_phone'      => $s['phone'],
            ]);
        }

        $this->command->info("Type2CompanySeeder: Salloni Donjeta created (id={$company->id}) with owner donjeta@termini.im / Password1");
    }
}
