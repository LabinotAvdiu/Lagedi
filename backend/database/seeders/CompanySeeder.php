<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyGalleryImage;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // =====================================================================
        // Test clients
        // =====================================================================
        User::create([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'test@test.com',
            'password'   => Hash::make('123456789'),
            'phone'      => '0600000000',
            'city'       => 'Paris',
        ]);

        User::create([
            'first_name' => 'Test',
            'last_name'  => 'Client',
            'email'      => 'client@test.com',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000001',
            'city'       => 'Paris',
        ]);

        // =====================================================================
        // Company 1 — Barbershop homme (Paris)
        // =====================================================================
        $company1 = $this->createCompany([
            'name'              => 'Le Barbier Parisien',
            'description'       => 'Barbershop traditionnel au coeur de Paris. Coupes classiques et modernes pour hommes.',
            'phone'             => '0145678901',
            'email'             => 'contact@barbier-parisien.fr',
            'address'           => '15 Rue de Rivoli',
            'city'              => 'Paris',
            'postal_code'       => '75001',
            'country'           => 'France',
            'gender'            => 'men',
            'profile_image_url' => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=800&q=80',
            'rating'            => 4.7,
            'review_count'      => 214,
            'price_level'       => 2,
        ], 2.3488, 48.8566);

        $this->createGalleryImages($company1->id, [
            'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=800&q=80',
            'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=800&q=80',
            'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=800&q=80',
        ]);

        $this->createOpeningHours($company1->id, '09:00', '19:00', [6]);

        // Service categories — scoped to company 1
        $c1CatCoupes = ServiceCategory::create(['company_id' => $company1->id, 'name' => 'Coupes']);
        $c1CatBarbe  = ServiceCategory::create(['company_id' => $company1->id, 'name' => 'Barbe']);
        $c1CatSoins  = ServiceCategory::create(['company_id' => $company1->id, 'name' => 'Soins']);

        // Owner
        $owner1 = User::create([
            'first_name' => 'Karim',
            'last_name'  => 'Benali',
            'email'      => 'karim@barbier-parisien.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000010',
            'city'       => 'Paris',
        ]);
        $cuOwner1 = CompanyUser::create([
            'company_id'  => $company1->id,
            'user_id'     => $owner1->id,
            'role'        => 'owner',
            'is_active'   => true,
            'specialties' => ['Coupe classique', 'Barbe au rasoir', 'Dégradé'],
        ]);
        $this->createEmployeeSchedule($cuOwner1->id, '09:00', '19:00', [6]);

        $emp1 = User::create([
            'first_name' => 'Mehdi',
            'last_name'  => 'Kaci',
            'email'      => 'mehdi@barbier-parisien.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000011',
            'city'       => 'Paris',
        ]);
        $cuEmp1 = CompanyUser::create([
            'company_id'  => $company1->id,
            'user_id'     => $emp1->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Coupe courte', 'Dégradé', 'Taille de barbe'],
        ]);
        $this->createEmployeeSchedule($cuEmp1->id, '09:00', '17:00', [5, 6]);

        $emp2 = User::create([
            'first_name' => 'Yassine',
            'last_name'  => 'Amrani',
            'email'      => 'yassine@barbier-parisien.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000012',
            'city'       => 'Paris',
        ]);
        $cuEmp2 = CompanyUser::create([
            'company_id'  => $company1->id,
            'user_id'     => $emp2->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Coupe afro', 'Tresses', 'Soin capillaire'],
        ]);
        $this->createEmployeeSchedule($cuEmp2->id, '10:00', '19:00', [0, 6]);

        $s1 = Service::create([
            'company_id'  => $company1->id,
            'category_id' => $c1CatCoupes->id,
            'name'        => 'Coupe homme',
            'description' => 'Coupe classique aux ciseaux ou tondeuse',
            'price'       => 25.00,
            'duration'    => 30,
            'gender'      => 'men',
        ]);
        $s2 = Service::create([
            'company_id'  => $company1->id,
            'category_id' => $c1CatCoupes->id,
            'name'        => 'Coupe + Barbe',
            'description' => 'Coupe complète avec taille de barbe au rasoir',
            'price'       => 40.00,
            'duration'    => 45,
            'gender'      => 'men',
        ]);
        $s3 = Service::create([
            'company_id'  => $company1->id,
            'category_id' => $c1CatBarbe->id,
            'name'        => 'Taille de barbe',
            'description' => 'Taille et sculpture de barbe au rasoir droit',
            'price'       => 15.00,
            'duration'    => 20,
            'gender'      => 'men',
        ]);
        $s4 = Service::create([
            'company_id'  => $company1->id,
            'category_id' => $c1CatSoins->id,
            'name'        => 'Soin capillaire',
            'description' => 'Masque nourrissant et massage du cuir chevelu',
            'price'       => 20.00,
            'duration'    => 30,
            'gender'      => 'men',
        ]);

        $cuOwner1->services()->attach([
            $s1->id => ['duration' => null],
            $s2->id => ['duration' => null],
            $s3->id => ['duration' => null],
            $s4->id => ['duration' => null],
        ]);
        $cuEmp1->services()->attach([
            $s1->id => ['duration' => 25],
            $s2->id => ['duration' => 40],
            $s3->id => ['duration' => 15],
        ]);
        $cuEmp2->services()->attach([
            $s1->id => ['duration' => 45],
            $s2->id => ['duration' => 60],
            $s3->id => ['duration' => 25],
            $s4->id => ['duration' => 40],
        ]);

        // =====================================================================
        // Company 2 — Salon mixte (Lyon)
        // =====================================================================
        $company2 = $this->createCompany([
            'name'              => 'Style & Couleur',
            'description'       => 'Salon de coiffure mixte spécialisé en colorations et coupes tendance.',
            'phone'             => '0478123456',
            'email'             => 'contact@style-couleur.fr',
            'address'           => '42 Rue de la République',
            'city'              => 'Lyon',
            'postal_code'       => '69002',
            'country'           => 'France',
            'gender'            => 'both',
            'profile_image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=800&q=80',
            'rating'            => 4.5,
            'review_count'      => 98,
            'price_level'       => 2,
        ], 4.8357, 45.7640);

        $this->createGalleryImages($company2->id, [
            'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=800&q=80',
            'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=800&q=80',
            'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80',
        ]);

        $this->createOpeningHours($company2->id, '09:30', '19:30', [0, 6]);

        $c2CatCoupes     = ServiceCategory::create(['company_id' => $company2->id, 'name' => 'Coupes']);
        $c2CatColoration = ServiceCategory::create(['company_id' => $company2->id, 'name' => 'Colorations']);
        $c2CatSoins      = ServiceCategory::create(['company_id' => $company2->id, 'name' => 'Soins']);

        $owner2 = User::create([
            'first_name' => 'Sophie',
            'last_name'  => 'Martin',
            'email'      => 'sophie@style-couleur.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000020',
            'city'       => 'Lyon',
        ]);
        $cuOwner2 = CompanyUser::create([
            'company_id'  => $company2->id,
            'user_id'     => $owner2->id,
            'role'        => 'owner',
            'is_active'   => true,
            'specialties' => ['Coupe femme', 'Coloration', 'Balayage', 'Soin Kératine'],
        ]);
        $this->createEmployeeSchedule($cuOwner2->id, '09:30', '19:30', [0, 6]);

        $emp3 = User::create([
            'first_name' => 'Julie',
            'last_name'  => 'Dupont',
            'email'      => 'julie@style-couleur.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000021',
            'city'       => 'Lyon',
        ]);
        $cuEmp3 = CompanyUser::create([
            'company_id'  => $company2->id,
            'user_id'     => $emp3->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Coloration', 'Mèches', 'Balayage'],
        ]);
        $this->createEmployeeSchedule($cuEmp3->id, '09:30', '18:00', [0, 4, 6]);

        $emp4 = User::create([
            'first_name' => 'Thomas',
            'last_name'  => 'Leroy',
            'email'      => 'thomas@style-couleur.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000022',
            'city'       => 'Lyon',
        ]);
        $cuEmp4 = CompanyUser::create([
            'company_id'  => $company2->id,
            'user_id'     => $emp4->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Coupe homme', 'Coupe femme', 'Brushing'],
        ]);
        $this->createEmployeeSchedule($cuEmp4->id, '11:00', '19:30', [0, 3, 6]);

        $s5 = Service::create([
            'company_id'  => $company2->id,
            'category_id' => $c2CatCoupes->id,
            'name'        => 'Coupe femme',
            'description' => 'Coupe et brushing inclus',
            'price'       => 45.00,
            'duration'    => 45,
            'gender'      => 'women',
        ]);
        $s6 = Service::create([
            'company_id'  => $company2->id,
            'category_id' => $c2CatCoupes->id,
            'name'        => 'Coupe homme',
            'price'       => 25.00,
            'duration'    => 30,
            'gender'      => 'men',
        ]);
        $s7 = Service::create([
            'company_id'  => $company2->id,
            'category_id' => $c2CatColoration->id,
            'name'        => 'Coloration complète',
            'description' => 'Coloration racines et longueurs',
            'price'       => 80.00,
            'duration'    => 120,
            'gender'      => 'both',
        ]);
        $s8 = Service::create([
            'company_id'  => $company2->id,
            'category_id' => $c2CatColoration->id,
            'name'        => 'Mèches / Balayage',
            'description' => 'Mèches ou balayage effet naturel',
            'price'       => 95.00,
            'duration'    => 150,
            'gender'      => 'both',
        ]);
        $s9 = Service::create([
            'company_id'  => $company2->id,
            'category_id' => $c2CatSoins->id,
            'name'        => 'Soin Kératine',
            'description' => 'Lissage kératine pour cheveux lisses pendant 3 mois',
            'price'       => 120.00,
            'duration'    => 90,
            'gender'      => 'both',
        ]);

        $cuOwner2->services()->attach([
            $s5->id => ['duration' => null],
            $s6->id => ['duration' => null],
            $s7->id => ['duration' => null],
            $s8->id => ['duration' => null],
            $s9->id => ['duration' => null],
        ]);
        $cuEmp3->services()->attach([
            $s5->id => ['duration' => 50],
            $s7->id => ['duration' => 100],
            $s8->id => ['duration' => 120],
            $s9->id => ['duration' => null],
        ]);
        $cuEmp4->services()->attach([
            $s5->id => ['duration' => 35],
            $s6->id => ['duration' => 20],
            $s7->id => ['duration' => null],
        ]);

        // =====================================================================
        // Company 3 — Salon femme haut de gamme (Paris)
        // =====================================================================
        $company3 = $this->createCompany([
            'name'              => 'Élégance Coiffure',
            'description'       => 'Salon haut de gamme dédié aux femmes. Spécialistes colorations et soins.',
            'phone'             => '0156789012',
            'email'             => 'contact@elegance-coiffure.fr',
            'address'           => '8 Avenue Montaigne',
            'city'              => 'Paris',
            'postal_code'       => '75008',
            'country'           => 'France',
            'gender'            => 'women',
            'profile_image_url' => 'https://images.unsplash.com/photo-1600948836101-f9ffda59d250?w=800&q=80',
            'rating'            => 4.9,
            'review_count'      => 341,
            'price_level'       => 3,
        ], 2.3030, 48.8660);

        $this->createGalleryImages($company3->id, [
            'https://images.unsplash.com/photo-1600948836101-f9ffda59d250?w=800&q=80',
            'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800&q=80',
            'https://images.unsplash.com/photo-1634302086460-5eea4f87e4af?w=800&q=80',
        ]);

        $this->createOpeningHours($company3->id, '10:00', '20:00', [0, 6]);

        $c3CatCoupes     = ServiceCategory::create(['company_id' => $company3->id, 'name' => 'Coupe & Coiffure']);
        $c3CatColoration = ServiceCategory::create(['company_id' => $company3->id, 'name' => 'Colorations & Mèches']);
        $c3CatSoins      = ServiceCategory::create(['company_id' => $company3->id, 'name' => 'Soins & Traitements']);

        $owner3 = User::create([
            'first_name' => 'Marie',
            'last_name'  => 'Laurent',
            'email'      => 'marie@elegance-coiffure.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000030',
            'city'       => 'Paris',
        ]);
        $cuOwner3 = CompanyUser::create([
            'company_id'  => $company3->id,
            'user_id'     => $owner3->id,
            'role'        => 'owner',
            'is_active'   => true,
            'specialties' => ['Coupe femme', 'Coloration premium', 'Mèches', 'Soin profond'],
        ]);
        $this->createEmployeeSchedule($cuOwner3->id, '10:00', '20:00', [0, 6]);

        $emp5 = User::create([
            'first_name' => 'Camille',
            'last_name'  => 'Bernard',
            'email'      => 'camille@elegance-coiffure.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000031',
            'city'       => 'Paris',
        ]);
        $cuEmp5 = CompanyUser::create([
            'company_id'  => $company3->id,
            'user_id'     => $emp5->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Balayage', 'Ombré hair', 'Soin Kératine'],
        ]);
        $this->createEmployeeSchedule($cuEmp5->id, '10:00', '18:30', [0, 2, 6]);

        $emp6 = User::create([
            'first_name' => 'Isabelle',
            'last_name'  => 'Moreau',
            'email'      => 'isabelle@elegance-coiffure.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000032',
            'city'       => 'Paris',
        ]);
        $cuEmp6 = CompanyUser::create([
            'company_id'  => $company3->id,
            'user_id'     => $emp6->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Coupe précision', 'Brushing', 'Chignon'],
        ]);
        $this->createEmployeeSchedule($cuEmp6->id, '11:00', '20:00', [0, 5, 6]);

        $s10 = Service::create([
            'company_id'  => $company3->id,
            'category_id' => $c3CatCoupes->id,
            'name'        => 'Coupe femme',
            'description' => 'Coupe précision avec brushing inclus',
            'price'       => 55.00,
            'duration'    => 60,
            'gender'      => 'women',
        ]);
        $s10b = Service::create([
            'company_id'  => $company3->id,
            'category_id' => $c3CatCoupes->id,
            'name'        => 'Brushing / Mise en plis',
            'description' => 'Brushing professionnel ou mise en plis',
            'price'       => 35.00,
            'duration'    => 30,
            'gender'      => 'women',
        ]);
        $s11 = Service::create([
            'company_id'  => $company3->id,
            'category_id' => $c3CatColoration->id,
            'name'        => 'Coloration premium',
            'description' => 'Coloration haut de gamme, racines et longueurs',
            'price'       => 110.00,
            'duration'    => 120,
            'gender'      => 'women',
        ]);
        $s11b = Service::create([
            'company_id'  => $company3->id,
            'category_id' => $c3CatColoration->id,
            'name'        => 'Balayage / Ombré',
            'description' => 'Technique balayage ou ombré hair naturel',
            'price'       => 130.00,
            'duration'    => 150,
            'gender'      => 'women',
        ]);
        $s12 = Service::create([
            'company_id'  => $company3->id,
            'category_id' => $c3CatSoins->id,
            'name'        => 'Soin profond',
            'description' => 'Masque restructurant et soin en profondeur',
            'price'       => 45.00,
            'duration'    => 45,
            'gender'      => 'women',
        ]);

        $cuOwner3->services()->attach([
            $s10->id  => ['duration' => null],
            $s10b->id => ['duration' => null],
            $s11->id  => ['duration' => null],
            $s11b->id => ['duration' => null],
            $s12->id  => ['duration' => null],
        ]);
        $cuEmp5->services()->attach([
            $s10->id  => ['duration' => 55],
            $s11->id  => ['duration' => 110],
            $s11b->id => ['duration' => 140],
            $s12->id  => ['duration' => null],
        ]);
        $cuEmp6->services()->attach([
            $s10->id  => ['duration' => 50],
            $s10b->id => ['duration' => 25],
            $s12->id  => ['duration' => null],
        ]);

        // =====================================================================
        // Company 4 — Barbershop budget (Marseille)
        // =====================================================================
        $company4 = $this->createCompany([
            'name'              => 'Fresh Cuts Marseille',
            'description'       => 'Barbershop moderne et accessible, ouvert 7j/7. Coupes homme et femme sans rendez-vous.',
            'phone'             => '0491234567',
            'email'             => 'contact@freshcuts-marseille.fr',
            'address'           => '3 Rue de la Canebière',
            'city'              => 'Marseille',
            'postal_code'       => '13001',
            'country'           => 'France',
            'gender'            => 'both',
            'profile_image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=800&q=80',
            'rating'            => 4.2,
            'review_count'      => 57,
            'price_level'       => 1,
        ], 5.3698, 43.2965);

        $this->createGalleryImages($company4->id, [
            'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=800&q=80',
            'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=800&q=80',
            'https://images.unsplash.com/photo-1512864084360-7c0c4d7b4509?w=800&q=80',
        ]);

        $this->createOpeningHours($company4->id, '09:00', '20:00', []);

        $c4CatCoupes = ServiceCategory::create(['company_id' => $company4->id, 'name' => 'Coupes']);
        $c4CatBarbe  = ServiceCategory::create(['company_id' => $company4->id, 'name' => 'Barbe']);

        $owner4 = User::create([
            'first_name' => 'Rachid',
            'last_name'  => 'Bouaziz',
            'email'      => 'rachid@freshcuts-marseille.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000040',
            'city'       => 'Marseille',
        ]);
        $cuOwner4 = CompanyUser::create([
            'company_id'  => $company4->id,
            'user_id'     => $owner4->id,
            'role'        => 'owner',
            'is_active'   => true,
            'specialties' => ['Coupe homme', 'Dégradé', 'Barbe', 'Coupe femme'],
        ]);
        $this->createEmployeeSchedule($cuOwner4->id, '09:00', '20:00', []);

        $emp7 = User::create([
            'first_name' => 'Amine',
            'last_name'  => 'Chaoui',
            'email'      => 'amine@freshcuts-marseille.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000041',
            'city'       => 'Marseille',
        ]);
        $cuEmp7 = CompanyUser::create([
            'company_id'  => $company4->id,
            'user_id'     => $emp7->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Dégradé américain', 'Coupe afro', 'Barbe sculptée'],
        ]);
        $this->createEmployeeSchedule($cuEmp7->id, '10:00', '20:00', [0]);

        $emp8 = User::create([
            'first_name' => 'Nassim',
            'last_name'  => 'Hadj',
            'email'      => 'nassim@freshcuts-marseille.fr',
            'password'   => Hash::make('Password1'),
            'phone'      => '0600000042',
            'city'       => 'Marseille',
        ]);
        $cuEmp8 = CompanyUser::create([
            'company_id'  => $company4->id,
            'user_id'     => $emp8->id,
            'role'        => 'employee',
            'is_active'   => true,
            'specialties' => ['Coupe femme', 'Brushing', 'Coupe enfant'],
        ]);
        $this->createEmployeeSchedule($cuEmp8->id, '09:00', '18:00', [3, 4]);

        $s13 = Service::create([
            'company_id'  => $company4->id,
            'category_id' => $c4CatCoupes->id,
            'name'        => 'Coupe homme',
            'description' => 'Coupe simple tondeuse ou ciseaux',
            'price'       => 15.00,
            'duration'    => 25,
            'gender'      => 'men',
        ]);
        $s14 = Service::create([
            'company_id'  => $company4->id,
            'category_id' => $c4CatCoupes->id,
            'name'        => 'Coupe femme',
            'description' => 'Coupe et mise en forme',
            'price'       => 20.00,
            'duration'    => 30,
            'gender'      => 'women',
        ]);
        $s14b = Service::create([
            'company_id'  => $company4->id,
            'category_id' => $c4CatCoupes->id,
            'name'        => 'Coupe enfant',
            'description' => 'Coupe pour enfants jusqu\'à 12 ans',
            'price'       => 10.00,
            'duration'    => 20,
            'gender'      => 'both',
        ]);
        $s15 = Service::create([
            'company_id'  => $company4->id,
            'category_id' => $c4CatBarbe->id,
            'name'        => 'Barbe express',
            'description' => 'Taille et contour de barbe rapide',
            'price'       => 10.00,
            'duration'    => 15,
            'gender'      => 'men',
        ]);
        $s15b = Service::create([
            'company_id'  => $company4->id,
            'category_id' => $c4CatBarbe->id,
            'name'        => 'Coupe + Barbe',
            'description' => 'Formule coupe + barbe express',
            'price'       => 22.00,
            'duration'    => 40,
            'gender'      => 'men',
        ]);

        $cuOwner4->services()->attach([
            $s13->id  => ['duration' => null],
            $s14->id  => ['duration' => null],
            $s14b->id => ['duration' => null],
            $s15->id  => ['duration' => null],
            $s15b->id => ['duration' => null],
        ]);
        $cuEmp7->services()->attach([
            $s13->id  => ['duration' => 20],
            $s14b->id => ['duration' => 15],
            $s15->id  => ['duration' => 12],
            $s15b->id => ['duration' => 30],
        ]);
        $cuEmp8->services()->attach([
            $s13->id  => ['duration' => 25],
            $s14->id  => ['duration' => 25],
            $s14b->id => ['duration' => 18],
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createCompany(array $data, float $lng, float $lat): Company
    {
        DB::statement(
            "INSERT INTO companies
                (name, description, profile_image_url, phone, email, address, city, postal_code, country, gender, rating, review_count, price_level, location, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_SRID(POINT(?, ?), 4326), NOW(), NOW())",
            [
                $data['name'],
                $data['description'] ?? null,
                $data['profile_image_url'] ?? null,
                $data['phone'],
                $data['email'],
                $data['address'],
                $data['city'],
                $data['postal_code'],
                $data['country'] ?? 'France',
                $data['gender'] ?? 'both',
                $data['rating'] ?? 0.00,
                $data['review_count'] ?? 0,
                $data['price_level'] ?? 2,
                $lng,
                $lat,
            ]
        );

        return Company::orderByDesc('id')->first();
    }

    private function createGalleryImages(int $companyId, array $urls): void
    {
        foreach ($urls as $index => $url) {
            CompanyGalleryImage::create([
                'company_id' => $companyId,
                'image_path' => $url,
                'sort_order' => $index,
            ]);
        }
    }

    private function createOpeningHours(int $companyId, string $open, string $close, array $closedDays = []): void
    {
        for ($day = 0; $day < 7; $day++) {
            CompanyOpeningHour::create([
                'company_id'  => $companyId,
                'day_of_week' => $day,
                'open_time'   => in_array($day, $closedDays) ? null : $open,
                'close_time'  => in_array($day, $closedDays) ? null : $close,
                'is_closed'   => in_array($day, $closedDays),
            ]);
        }
    }

    private function createEmployeeSchedule(int $companyUserId, string $start, string $end, array $offDays = []): void
    {
        for ($day = 0; $day < 7; $day++) {
            if (in_array($day, $offDays)) {
                continue;
            }
            EmployeeSchedule::create([
                'company_user_id' => $companyUserId,
                'day_of_week'     => $day,
                'start_time'      => $start,
                'end_time'        => $end,
            ]);
        }
    }
}
