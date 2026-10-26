<?php

use Illuminate\Support\Facades\Route;

// Alleen een API: geen Vite-assets in de image, dus geen welcome-view.
Route::get('/', fn () => response()->json(['name' => 'VeiligerBuiten API']));
