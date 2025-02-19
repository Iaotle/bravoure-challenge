<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class FullCountrySeeder extends Seeder {
	/**
	 * Run the database seeds.
	 */
	public function run(): void {
		// clear the countries table first:
		\App\Models\Country::truncate();

        $countries = Http::get('https://restcountries.com/v3.1/all?fields=cca2,name,altSpellings')->json();
		foreach ($countries as $country) {
			\App\Models\Country::create([
				'name' => $country['name']['common'],
				// 'official_name' => $country['name']['official'],
				'iso_alpha_2' => $country['cca2'],
				// 'alt_spellings' => json_encode($country['altSpellings']),
			]);
		}
	}
}
