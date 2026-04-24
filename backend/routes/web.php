<?php

use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
