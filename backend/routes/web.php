<?php

use App\Http\Controllers\ShareController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Social share — Open Graph SSR for /company/{id}
|--------------------------------------------------------------------------
| Crawler-only HTML stub with proper og:* / twitter:* meta tags. Nginx on
| www.termini-im.com sniffs User-Agent and reverse-proxies bots here; real
| users always hit the Flutter SPA directly.
|
| Routes:
|   GET /share/company/{id}                — base salon card
|   GET /share/company/{id}/employee/{eid} — same, with ?employee= preserved
*/
Route::get('/share/company/{id}', [ShareController::class, 'company'])
    ->name('share.company')
    ->middleware('throttle:60,1');

Route::get('/share/company/{id}/employee/{employeeId}', [ShareController::class, 'company'])
    ->name('share.company.employee')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| D24 — Unsubscribe 1-click RFC 8058
|--------------------------------------------------------------------------
| GET  /unsubscribe?token=...  → page de confirmation blade
| POST /unsubscribe?token=...  → 1-click RFC 8058 (corps vide, 200)
|
| Pas d'auth requise — la signature Laravel garantit l'authenticité du token.
*/
Route::match(['GET', 'POST'], '/unsubscribe', [UnsubscribeController::class, 'handle'])
    ->name('unsubscribe')
    ->middleware('throttle:20,1');
