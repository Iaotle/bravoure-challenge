<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WikipediaService {
	public static function getExtract(string $countryCode): string {
		// Map country code to Wikipedia article title.
		$mapping = [
			'uk' => 'United Kingdom',
			'nl' => 'Netherlands',
			'de' => 'Germany',
			'fr' => 'France',
			'es' => 'Spain',
			'it' => 'Italy',
			'gr' => 'Greece',
		];
		$article = $mapping[$countryCode] ?? $countryCode;

		// Cache the extract for 30 days.
		return Cache::remember("wikipedia_{$article}", now()->addDays(30), function () use ($article) {
			$url = 'https://en.wikipedia.org/w/api.php';
			$params = [
				'action' => 'query',
				'prop' => 'extracts',
				'exintro' => 1,
				'explaintext' => 1,
				'format' => 'json',
				'titles' => $article,
			];
			$response = Http::get($url, $params);
			$json = $response->json();

			// The response contains a "pages" object keyed by page IDs.
			if (isset($json['query']['pages'])) {
				$pages = $json['query']['pages'];
				$page = reset($pages);
				return $page['extract'] ?? '';
			}

			return '';
		});
	}
}
