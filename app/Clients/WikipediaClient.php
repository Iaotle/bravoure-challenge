<?php
declare(strict_types=1);

namespace App\Clients;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HTTPClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\WikipediaClientException;
use App\Models\Country;

class WikipediaClient {
	private const API_URL = 'https://en.wikipedia.org/w/api.php';
	private const CACHE_DURATION_MINUTES = 60 * 24; // Cache for 24 hours, this will speed up the application and reduce the number of API/DB calls.

	public function __construct(private HTTPClient $http, private CacheRepository $cache) {}

	/**
	 * Get the Wikipedia extract for a given country code.
	 *
	 * @param string $countryCode
	 * @return string
	 *
	 * @throws WikipediaClientException
	 */
	public function getExtract(string $countryCode): string {
		$cacheKey = 'wikipedia_' . strtolower($countryCode);
		$tags = ['wikipedia', 'country-' . strtolower($countryCode)];
		$cache = $this->cache;

		if ($cache->supportsTags()) {
			$cache->tags($tags);
		}

	
		return $cache->remember(
			$cacheKey,
			now()->addMinutes(self::CACHE_DURATION_MINUTES),
			function () use ($countryCode) {
				$country = Country::where('iso_alpha_2', $countryCode)->first();
				if (!$country) {
					throw new WikipediaClientException("Country '{$countryCode}' not found.");
				}
				if ($country->description) {
					return $country->description;
				}
				$extract = $this->fetchExtractFromWikipedia($country->name);
				$country->description = $extract;
				$country->save();
				return $extract;
			}
		);
	}

	/**
	 * Fetch the Wikipedia extract for the given article.
	 *
	 * @param string $article
	 * @return string
	 *
	 * @throws WikipediaClientException
	 */
	protected function fetchExtractFromWikipedia(string $article): string {
		$article = str_replace(' ', '_', $article);
		$params = [
			'action' => 'query',
			'prop' => 'extracts',
			'exintro' => 1,
			'explaintext' => 1,
			'format' => 'json',
			'titles' => $article,
		];

		try {
			$response = $this->http->get(self::API_URL, $params)->throw();
		} catch (RequestException $e) {
			Log::error('Wikipedia API call failed', [
				'article' => $article,
				'error' => $e->getMessage(),
			]);
			throw new WikipediaClientException("Failed to fetch extract for '{$article}'.", 0, $e);
		}

		$json = $response?->json();

		// Check if the API returned an error structure
		if (isset($json['error'])) {
			Log::error('Wikipedia API returned an error', [
				'article' => $article,
				'error' => $json['error'],
			]);
			throw new WikipediaClientException("Wikipedia API error for '{$article}': " . json_encode($json['error']));
		}

		if (isset($json['query']['pages'])) {
			$pages = $json['query']['pages'];
			$page = reset($pages);
			if (isset($page['extract'])) {
				return $page['extract'];
			}
		}

		// If we reach this point, something went wrong with the expected structure
		Log::error("No extract found for article '{$article}'.", ['response' => $json]);
		throw new WikipediaClientException("No extract found for '{$article}'.");
	}
}
