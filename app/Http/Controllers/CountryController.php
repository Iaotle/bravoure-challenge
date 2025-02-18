<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WikipediaService;
use App\Services\YouTubeService;

class CountryController extends Controller {
	public function index(Request $request) {
		// Get query parameters with defaults.
		$countryParam = $request->query('country');

		// List of country codes we support.
		$countries = ['uk', 'nl', 'de', 'fr', 'es', 'it', 'gr'];
		if ($countryParam && in_array($countryParam, $countries)) {
			$countries = [$countryParam];
		}

		$data = [];
		foreach ($countries as $code) {
			// Fetch and cache Wikipedia extract.
			$wikipediaExtract = WikipediaService::getExtract($code); // string or ''.

			// Fetch and cache YouTube popular videos.
			$youtubeVideos = YouTubeService::getPopularVideos($code); // array or [].

			$data[] = [
				'country' => $code,
				'wikipedia_extract' => $wikipediaExtract,
				'videos' => $youtubeVideos,
			];
		}

		return response()->json([
			'data' => $data,
		]);
	}
}
