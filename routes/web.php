<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CountryController;
use App\Models\Country;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

Route::get('/', function () {
	return view('welcome')->with('countries', Country::pluck('name', 'iso_alpha_2'));
});

// 2 routes, one to run CountrySeeder, and one to run FullCountrySeeder
Route::get('/seed-countries', function () {
	Artisan::call('db:seed', ['--class' => 'CountrySeeder']);
	return 'Countries seeded';
});

Route::get('/seed-full-countries', function () {
	Artisan::call('db:seed', ['--class' => 'FullCountrySeeder']);
	return 'Full countries seeded';
});

Route::get('/clear-cache', function () {
	Cache::flush();
	return 'Cache cleared';
});

Route::get('/clear-country-cache/{country}', function ($country) {
	$countryTag = 'country-' . strtolower($country);
	// Flush wikipedia and YouTube caches for the specified country
	Cache::tags([$countryTag])->flush();
	// Optionally, reset the country description in your database
	Country::where('iso_alpha_2', $country)->update(['description' => null]);
	return 'Country cache cleared';
});

Route::get('/clear-youtube-cache', function () {
	Cache::tags('youtube')->flush();
	return 'YouTube cache cleared';
});

Route::get('/clear-wikipedia-cache', function () {
	Cache::tags('wikipedia')->flush();
	return 'Wikipedia cache cleared';
});

Route::get('/clear-country-description', function () {
	Country::query()->update(['description' => null]);
	return 'Country descriptions cleared';
});

Route::get('/countries', [CountryController::class, 'index']);

Route::get('/test-cache', function () {
	$cache = cache()->store('redis');
	$cache->put('test-key', 'works', 10);
	return $cache->get('test-key'); // Should return "works"
});
