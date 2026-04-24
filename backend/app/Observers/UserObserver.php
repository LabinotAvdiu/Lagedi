<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

/**
 * D19 — Seed des préférences de notification par défaut à la création d'un user.
 *
 * L'insertion est non-transactionnelle et utilise insertOrIgnore pour éviter
 * les doublons si l'observer est déclenché plusieurs fois (re-seed, tests…).
 */
class UserObserver
{
    public function created(User $user): void
    {
        $user->seedDefaultNotificationPreferences();
    }
}
