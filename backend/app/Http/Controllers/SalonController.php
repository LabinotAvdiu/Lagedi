<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SalonController extends Controller
{
    public function search(Request $request)
    {
        $request->validate(['city' => 'required|string|max:100']);

        $city   = $request->query('city');
        $apiKey = config('services.google_places.key');

        $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
            'query'    => "barbier coiffeur {$city}",
            'type'     => 'hair_salon',
            'language' => 'fr',
            'key'      => $apiKey,
        ]);

        if (! $response->ok()) {
            return response()->json(['error' => 'Erreur lors de la recherche.'], 502);
        }

        $data   = $response->json();
        $status = $data['status'] ?? '';

        if ($status === 'REQUEST_DENIED' || $status === 'INVALID_REQUEST') {
            return response()->json(['error' => 'Requête invalide.'], 502);
        }

        if ($status === 'ZERO_RESULTS') {
            return response()->json(['salons' => []]);
        }

        $salons = collect($data['results'] ?? [])->map(function ($place) {
            $priceRange = match ($place['price_level'] ?? null) {
                0, 1    => '€',
                2       => '€€',
                3       => '€€€',
                4       => '€€€€',
                default => null,
            };

            return [
                'id'          => $place['place_id'],
                'name'        => $place['name'],
                'address'     => $place['formatted_address'],
                'rating'      => $place['rating'] ?? null,
                'reviewCount' => $place['user_ratings_total'] ?? 0,
                'priceRange'  => $priceRange,
                'lat'         => $place['geometry']['location']['lat'],
                'lng'         => $place['geometry']['location']['lng'],
                'openNow'     => $place['opening_hours']['open_now'] ?? null,
                'photoRef'    => $place['photos'][0]['photo_reference'] ?? null,
            ];
        })->values();

        return response()->json(['salons' => $salons]);
    }

    public function photo(Request $request)
    {
        $request->validate([
            'ref' => ['required', 'string', 'max:2000', 'regex:/^[\w\-+\/=]+$/'],
        ]);

        $apiKey   = config('services.google_places.key');
        $photoRef = $request->query('ref');

        $response = Http::get('https://maps.googleapis.com/maps/api/place/photo', [
            'maxwidth'       => 400,
            'photoreference' => $photoRef,
            'key'            => $apiKey,
        ]);

        if (! $response->ok()) {
            return response()->json(['error' => 'Photo non disponible.'], 404);
        }

        return response($response->body(), 200)
            ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
