<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CountryController;

Route::get('/available-countries', function () {
	$countries = ['uk', 'nl', 'de', 'fr', 'es', 'it', 'gr'];
	return $countries;
});

Route::get('/ISO-3166-1-alpha-2', function () {
    $countries = [
        'uk' => 'United Kingdom',
        'nl' => 'Netherlands',
        'de' => 'Germany',
        'fr' => 'France',
        'es' => 'Spain',
        'it' => 'Italy',
        'gr' => 'Greece',
    ];
    return $countries;
});

Route::get('/', function () {
	return view('welcome')->with('countries', ['uk', 'nl', 'de', 'fr', 'es', 'it', 'gr']);
});

Route::get('/countries', [CountryController::class, 'index']);
