<?php

use Illuminate\Support\Facades\Route;

// Catch-all: serve the Vue SPA for every non-API route.
// Vue Router handles all client-side navigation.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
