<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Company model into the JSON shape expected by the Flutter listing cards.
 *
 * Shape:
 * {
 *   "id":             "1",
 *   "name":           "Salon Élégance",
 *   "address":        "39 Rue de la Bourse, Paris",
 *   "city":           "Paris",
 *   "photoUrl":       "https://...",
 *   "rating":         4.8,
 *   "reviewCount":    123,
 *   "priceLevel":     3,
 *   "morningSlots":   [{ "label": "Mer.16", "date": "2026-04-16", "available": true }, ...],
 *   "afternoonSlots": [{ "label": "Mer.16", "date": "2026-04-16", "available": true }, ...]
 * }
 */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => (string) $this->id,
            'name'           => $this->name,
            'address'        => $this->address,
            'city'           => $this->city,
            'photoUrl'       => $this->profile_image_url,
            'rating'         => (float) $this->rating,
            'reviewCount'    => (int) $this->review_count,
            'priceLevel'     => (int) $this->price_level,
            'morningSlots'   => $this->buildMorningSlots(),
            'afternoonSlots' => $this->buildAfternoonSlots(),
        ];
    }

    /**
     * Generates 5 morning slot stubs for the next 5 days.
     * Slots are placeholders — real availability will be computed
     * once the booking/slots engine is wired in.
     */
    private function buildMorningSlots(): array
    {
        return $this->buildSlots(days: 5, available: true);
    }

    /**
     * Generates 5 afternoon slot stubs for the next 5 days.
     */
    private function buildAfternoonSlots(): array
    {
        return $this->buildSlots(days: 5, available: true);
    }

    /**
     * Builds an array of slot stubs for the next N days starting tomorrow.
     *
     * @param  int  $days
     * @param  bool $available  Default availability until real slot logic is wired
     * @return array<int, array{label: string, date: string, available: bool}>
     */
    private function buildSlots(int $days, bool $available): array
    {
        $slots = [];
        $locale = app()->getLocale(); // respects app locale (fr/en)

        for ($i = 1; $i <= $days; $i++) {
            $date  = Carbon::today()->addDays($i);
            $slots[] = [
                'label'     => $this->formatSlotLabel($date, $locale),
                'date'      => $date->toDateString(),
                'available' => $available,
            ];
        }

        return $slots;
    }

    /**
     * Formats "Mer.16" style labels:
     *  - French locale → day abbreviation in French (Lun, Mar, Mer, Jeu, Ven, Sam, Dim)
     *  - Fallback → D.dd
     */
    private function formatSlotLabel(Carbon $date, string $locale): string
    {
        $frDays = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        if (str_starts_with($locale, 'fr')) {
            $abbreviation = $frDays[(int) $date->format('w')];
        } else {
            $abbreviation = $date->format('D'); // Mon, Tue, Wed…
        }

        return $abbreviation . '.' . $date->format('d');
    }
}
