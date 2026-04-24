<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Envoie une notification FCM de test — utile pour valider en prod que
 * toute la chaîne est saine après un déploiement :
 * device token bien enregistré + FIREBASE_CREDENTIALS présent + réseau FCM OK.
 *
 * Usage :
 *   php artisan fcm:test-send                # cible le device le plus récemment vu
 *   php artisan fcm:test-send --email=X      # cible le dernier device de cet email
 *   php artisan fcm:test-send --user=42      # cible le dernier device de ce user_id
 */
class FcmTestSendCommand extends Command
{
    protected $signature   = 'fcm:test-send
                              {--email= : Email du user cible}
                              {--user= : ID du user cible}';
    protected $description = 'Envoie une notification FCM de test au device le plus récent.';

    public function handle(FcmService $fcm): int
    {
        $user = $this->resolveTargetUser();
        if (! $user) {
            $this->error('Aucun user trouvé. Options : --email=... ou --user=...');
            return self::FAILURE;
        }

        $device = $user->devices()->latest('last_seen_at')->first();
        if (! $device instanceof UserDevice) {
            $this->error("Aucun device FCM enregistré pour {$user->email}.");
            $this->line('→ Ouvre l\'app sur un téléphone connecté à ce compte et vérifie que POST /api/me/devices passe.');
            return self::FAILURE;
        }

        $this->info("→ Target: {$user->email} | platform={$device->platform} | last_seen={$device->last_seen_at}");

        try {
            $fcm->sendToUser(
                user: $user,
                type: 'appointment.confirmed',
                data: [
                    'type'          => 'appointment.confirmed',
                    'appointmentId' => '999',
                    'companyId'     => '1',
                ],
                titleKey:   'appointment_confirmed_title',
                bodyKey:    'appointment_confirmed_body',
                bodyParams: [
                    'service_name' => 'Notif test',
                    'time'         => '14:00',
                ],
            );
        } catch (\Throwable $e) {
            $this->error('Exception durant sendToUser : ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('✓ sendToUser a retourné sans exception.');
        $this->line('→ Vérifie la réception côté téléphone. Si rien ne sonne, consulte storage/logs/laravel.log pour "[FCM] ..." lignes.');
        return self::SUCCESS;
    }

    private function resolveTargetUser(): ?User
    {
        $email = $this->option('email');
        if ($email) {
            return User::where('email', $email)->first();
        }

        $userId = $this->option('user');
        if ($userId) {
            return User::find($userId);
        }

        // Fallback : le user du device le plus récemment vu.
        $device = UserDevice::orderByDesc('last_seen_at')->first();
        return $device ? User::find($device->user_id) : null;
    }
}
