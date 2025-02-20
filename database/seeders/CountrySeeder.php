<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class CountrySeeder extends Seeder {
	/**
	 * Run the database seeds.
	 */
	public function run(): void {
		// clear the countries table first:
		\App\Models\Country::truncate();

		// $countries = Http::timeout(100)->retry(10)->get('https://restcountries.com/v3.1/alpha?codes=gb,nl,de,frt,es,it,gr&fields=cca2,name,altSpellings')->json();

		$countries = [
			[
				'name' => [
					'common' => 'Italy',
					'official' => 'Italian Republic',
					'nativeName' => [
						'ita' => [
							'official' => 'Repubblica italiana',
							'common' => 'Italia',
						],
					],
				],
				'cca2' => 'IT',
				'altSpellings' => ['IT', 'Italian Republic', 'Repubblica italiana'],
			],
			[
				'name' => [
					'common' => 'Netherlands',
					'official' => 'Kingdom of the Netherlands',
					'nativeName' => [
						'nld' => [
							'official' => 'Koninkrijk der Nederlanden',
							'common' => 'Nederland',
						],
					],
				],
				'cca2' => 'NL',
				'altSpellings' => ['NL', 'Holland', 'Nederland', 'The Netherlands'],
			],
			[
				'name' => [
					'common' => 'Greece',
					'official' => 'Hellenic Republic',
					'nativeName' => [
						'ell' => [
							'official' => 'Ελληνική Δημοκρατία',
							'common' => 'Ελλάδα',
						],
					],
				],
				'cca2' => 'GR',
				'altSpellings' => ['GR', 'Elláda', 'Hellenic Republic', 'Ελληνική Δημοκρατία'],
			],
			[
				'name' => [
					'common' => 'Spain',
					'official' => 'Kingdom of Spain',
					'nativeName' => [
						'spa' => [
							'official' => 'Reino de España',
							'common' => 'España',
						],
					],
				],
				'cca2' => 'ES',
				'altSpellings' => ['ES', 'Kingdom of Spain', 'Reino de España'],
			],
			[
				'name' => [
					'common' => 'Germany',
					'official' => 'Federal Republic of Germany',
					'nativeName' => [
						'deu' => [
							'official' => 'Bundesrepublik Deutschland',
							'common' => 'Deutschland',
						],
					],
				],
				'cca2' => 'DE',
				'altSpellings' => ['DE', 'Federal Republic of Germany', 'Bundesrepublik Deutschland'],
			],
			[
				'name' => [
					'common' => 'United Kingdom',
					'official' => 'United Kingdom of Great Britain and Northern Ireland',
					'nativeName' => [
						'eng' => [
							'official' => 'United Kingdom of Great Britain and Northern Ireland',
							'common' => 'United Kingdom',
						],
					],
				],
				'cca2' => 'GB',
				'altSpellings' => ['GB', 'UK', 'Great Britain'],
			],
		];
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
