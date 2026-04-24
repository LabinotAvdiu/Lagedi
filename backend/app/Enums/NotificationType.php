<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types de notifications configurables (opt-outable).
 *
 * LES TYPES TRANSACTIONNELS NE SONT PAS ICI — ils sont toujours envoyés
 * et ne figurent jamais dans notification_preferences.
 *
 * Transactionnels (hardcodés ailleurs) :
 *   - appointment.confirmed
 *   - appointment.cancelled_by_client
 *   - appointment.cancelled_by_owner
 *   - appointment.rejected
 *   - appointment.rescheduled_by_client
 *   - appointment.rescheduled_by_owner
 *   - appointment.created (owner/employee)
 *   - appointment.walk_in_created (owner)
 *   - support.reply
 *   - otp / password_reset / welcome (via email uniquement)
 */
class NotificationType
{
    // -------------------------------------------------------------------------
    // Rappels RDV — client
    // -------------------------------------------------------------------------

    /** Rappel RDV J-1 soir */
    public const REMINDER_EVENING = 'reminder_evening';

    /** Rappel RDV 2h avant */
    public const REMINDER_2H = 'reminder_2h';

    /** Demande d'avis J+1 après RDV confirmé */
    public const REVIEW_REQUEST = 'review_request';

    // -------------------------------------------------------------------------
    // Communauté — owner
    // -------------------------------------------------------------------------

    /** Nouvel avis publié sur le salon */
    public const NEW_REVIEW = 'new_review';

    /** Capacité journalière atteinte */
    public const CAPACITY_FULL = 'capacity_full';

    /** Récap hebdomadaire de l'activité */
    public const WEEKLY_DIGEST = 'weekly_digest';

    /** Rapport mensuel */
    public const MONTHLY_REPORT = 'monthly_report';

    // -------------------------------------------------------------------------
    // Favoris — client
    // -------------------------------------------------------------------------

    /** Nouveau(x) photo(s) sur un salon favori */
    public const FAVORITE_NEW_PHOTOS = 'favorite_new_photos';

    /** Nouveaux créneaux disponibles sur un salon favori */
    public const FAVORITE_NEW_SLOTS = 'favorite_new_slots';

    // -------------------------------------------------------------------------
    // Marketing (meta-type) — client + owner
    // -------------------------------------------------------------------------

    /** Newsletter, diaspora campaign, re-engagement… */
    public const MARKETING = 'marketing';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Tous les types configurables — liste canonique.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::REMINDER_EVENING,
            self::REMINDER_2H,
            self::REVIEW_REQUEST,
            self::NEW_REVIEW,
            self::CAPACITY_FULL,
            self::WEEKLY_DIGEST,
            self::MONTHLY_REPORT,
            self::FAVORITE_NEW_PHOTOS,
            self::FAVORITE_NEW_SLOTS,
            self::MARKETING,
        ];
    }

    /**
     * Types NON-transactionnels qui passent par les gates (quiet hours, dedup, frequency cap).
     *
     * On exclut reminder_evening et reminder_2h : ce sont des rappels
     * opt-outable mais jugés suffisamment importants pour ne pas être
     * soumis aux quiet hours (l'utilisateur les a demandés implicitement
     * en prenant un RDV).
     *
     * @return string[]
     */
    public static function gated(): array
    {
        return [
            self::REVIEW_REQUEST,
            self::NEW_REVIEW,
            self::CAPACITY_FULL,
            self::WEEKLY_DIGEST,
            self::MONTHLY_REPORT,
            self::FAVORITE_NEW_PHOTOS,
            self::FAVORITE_NEW_SLOTS,
            self::MARKETING,
        ];
    }

    /**
     * Types transactionnels hardcodés — toujours envoyés,
     * jamais dans notification_preferences, jamais soumis aux gates.
     *
     * @return string[]
     */
    public static function transactional(): array
    {
        return [
            'appointment.confirmed',
            'appointment.created',
            'appointment.cancelled_by_client',
            'appointment.cancelled_by_owner',
            'appointment.rejected',
            'appointment.rescheduled_by_client',
            'appointment.rescheduled_by_owner',
            'appointment.walk_in_created',
            'appointment.reminder_owner',
            'support.reply',
        ];
    }

    public static function isTransactional(string $type): bool
    {
        return in_array($type, self::transactional(), true);
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
