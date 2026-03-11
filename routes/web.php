<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SharedLinkController;
 

Route::get('/', function () {
    return view('welcome');
});


// El mapa que verá el cliente de Patricio
Route::get('/track-live/{token}', function ($token) {
    return view('public_tracking', ['token' => $token]);
});

// La API que alimentará ese mapa (sin necesidad de login)
Route::get('/api/public-location/{token}', [SharedLinkController::class, 'getPublicLocation']);