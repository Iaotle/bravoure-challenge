<?php

namespace App\Http\Controllers;

use App\Clients\WikipediaClient;
use App\Clients\YouTubeClient;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Services\WikipediaService;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;

class CountryController extends Controller {
	public function __construct(protected WikipediaClient $wikipedia, protected YouTubeClient $youtube) {}

	public function index(Request $request) {
		// Get query parameters with defaults.
		$countryParam = $request->query('country');
		$countryParam = strtoupper($countryParam);
		// params for pagination for the videos
		$pageToken = $request->query('pageToken', 0);
		$maxResults = $request->query('maxResults', 5);

		if (!is_numeric($pageToken) || $pageToken < 0) {
			return response()->json(
				[
					'error' => 'Invalid pageToken: ' . $pageToken . '. pageToken must be a positive number.',
				],
				400,
			);
		}

		// validate maxResults [1, 50]
		if ($maxResults < 1) {
			$maxResults = 5;
			// } elseif ($maxResults > 50) {
			// 	$maxResults = 50;
		}
		// List of country codes we support.
		$countries = Country::pluck('iso_alpha_2')->toArray();
		if ($countryParam && in_array($countryParam, $countries)) {
			$countries = [$countryParam];
		} elseif ($countryParam) {
			return response()->json(
				[
					'error' =>
						'Invalid country code: ' . $countryParam . '. Valid country codes are: ' . implode(', ', $countries),
				],
				400,
			);
		}
		$data = [];
		foreach ($countries as $code) {
			$wikipediaExtract = $this->wikipedia->getExtract($code);
			$youtubeVideos = $this->youtube->getPopularVideos($code, $pageToken, $maxResults);

			$result = [
				'country' => $code,
				'wikipedia_extract' => $wikipediaExtract,
			];
			$result = array_merge($result, $youtubeVideos);
			// dd($youtubeVideos);
			$data[] = $result;
		}

		return response()->json([
			'data' => $data,
		]);
	}
}
