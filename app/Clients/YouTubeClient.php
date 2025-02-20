<?php

namespace App\Clients;

use App\Jobs\Prefetcher;
use App\Services\TokenService;
use ErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpClient;
class YouTubeClient {
	protected $apiKey;
	protected $apiUrl;
	protected $cacheDuration;
	const MAX_RESULTS = 50; // max allowed by YouTube API, one query counts as 1 request for the purposes of rate limiting

	public function __construct(private HttpClient $http, private CacheRepository $cache, public $tokenService) {
		$this->apiKey = config('services.youtube.api_key');
		$this->apiUrl = config('services.youtube.api_url', 'https://www.googleapis.com/youtube/v3/videos');
		$this->cacheDuration = (int) config('services.youtube.cache_duration_minutes', 20); // in minutes
	}

	public function getPopularVideos(string $countryCode, ?int $pageToken = 0, ?int $maxResults = self::MAX_RESULTS): array {
		$countryCode = strtolower($countryCode);

		// Interpret $pageToken as the starting index and $maxResults as the ending index.
		// For example, pageToken=10 and maxResults=20 means "give me videos 10 through 29" (20 videos).
		$startIndex = $pageToken ?? 0;
		$endIndex = $startIndex + $maxResults;

		if ($endIndex <= $startIndex) {
			throw new \InvalidArgumentException(
				'The ending index (maxResults) must be greater than the starting index (pageToken).',
			);
		}

		$requestedCount = $endIndex - $startIndex;
		$pageSize = self::MAX_RESULTS; // 50 videos per cached page

		// Determine which cached pages we need.
		$firstPageNumber = (int) floor($startIndex / self::MAX_RESULTS);
		$lastPageNumber = (int) floor(($endIndex - 1) / self::MAX_RESULTS);

		$allVideos = [];
		$totalResults = 0;
		$youTubeNextToken = null;
		$youTubePrevToken = null;
		// TODO: handle rate limits? I never trigger them :)

		// Loop over each needed page.
		$cachedAt = now();
		$dispatched = false;
		$freshPages = 0;
		for ($page = $firstPageNumber; $page <= $lastPageNumber; $page++) {
			// We use our helper "mapLineToToken" to get the YouTube API page token.
			$lineNumber = $page * self::MAX_RESULTS;
			$youTubePageToken = $this->tokenService->getTokenFromOffset($lineNumber);
			if (!$youTubePageToken) {
				// If we don't have a token for this page, we can't fetch it.
				break;
			}
			$tags = ['youtube', 'country-' . $countryCode];

			$cacheKey = "youtube_{$countryCode}_{$youTubePageToken}";
			$cache = $this->cache;

			if ($cache->supportsTags()) {
				$cache->tags($tags);
			}
			$now = now();
			$cachedPage = $cache->remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use (
				$countryCode,
				$youTubePageToken,
				$now,
			) {
				$params = [
					'chart' => 'mostPopular',
					'regionCode' => strtoupper($countryCode),
					'part' => 'snippet',
					'maxResults' => self::MAX_RESULTS, // always 50 per API call
					'key' => $this->apiKey,
					'pageToken' => $youTubePageToken,
				];
				// TODO: laravel-ratelimit this route so we don't hit google API unnecessarily?

				$response = $this->http->get($this->apiUrl, $params);

				if ($response->failed()) {
					Log::error('YouTube API request failed', ['response' => $response->body()]);
					return [
						'videos' => [],
						'error' => $response->json()['error'] ?? 'Unknown error',
						'cachedAt' => $now,
						'nextToken' => null,
						'prevToken' => null,
						'totalResults' => 0,
						'numResults' => 0,
						'youTubeNextToken' => null,
						'youTubePrevToken' => null,
					];
				}
				return $this->processApiResponse($response->json(), $now);
			});
			$pageIsFresh = $now === $cachedPage['cachedAt'];
			$freshPages += $pageIsFresh ? 1 : 0;
			// if we just cached this page, and this page is the last page requested,
			// dispatch a prefetch job to fetch the next page. We do it because we
			// want to maintain a consistent cache per country (no duplicates of videos for example)
			// by fetching the whole set of videos in one go.
			if ($pageIsFresh && $page === $lastPageNumber && $cachedPage['youTubeNextToken']) {
				// TODO: setup FastCGI to use dispatchAfterResponse because we don't want to do it synchronously
				Prefetcher::dispatch($cachedPage['youTubeNextToken'], $countryCode);
				$dispatched = true;
			}

			// dd(5);
			// $dispatched['prefetcher'];

			// For the first fetched page record total available results and YouTube’s API tokens.
			if (empty($allVideos)) {
				$totalResults = $cachedPage['totalResults'];
				$youTubeNextToken = $cachedPage['youTubeNextToken'];
				$youTubePrevToken = $cachedPage['youTubePrevToken'];
				// cap the last page number based on the total results to avoid fetching empty pages
				$lastPageNumber = min($lastPageNumber, (int) ceil($totalResults / self::MAX_RESULTS));
			}
			$when_cached = $cachedPage['cachedAt'] ?? null;
			// get the oldest cached_at timestamp
			$cachedAt = $when_cached && $when_cached < $cachedAt ? $when_cached : $cachedAt;
			// Append the videos from this cached page.

			if (isset($cachedPage['error'])) {
				// dd($cachedPage['error']);
				// array:3 [ // app/Clients/YouTubeClient.php:130
				// 	"code" => 403
				// 	"message" => "The request cannot be completed because you have exceeded your <a href="/youtube/v3/getting-started#quota">quota</a>."
				// 	"errors" => array:1 [
				// 	  0 => array:3 [
				// 		"message" => "The request cannot be completed because you have exceeded your <a href="/youtube/v3/getting-started#quota">quota</a>."
				// 		"domain" => "youtube.quota"
				// 		"reason" => "quotaExceeded"
				// 	  ]
				// 	]
				//   ]
				// report error from above
				
			}
			$allVideos = array_merge($allVideos, $cachedPage['videos']);
		}

		// Our combined $allVideos covers from index ($firstPageNumber * 50)
		// Calculate the offset into the first fetched page:
		$offsetWithinPage = $startIndex - $firstPageNumber * $pageSize;
		$videosSlice = array_slice($allVideos, $offsetWithinPage, $requestedCount);
		// dd(count($videosSlice), count($allVideos));
		// Compute new pagination tokens based on the indices.
		$nextToken = $endIndex < $totalResults ? $endIndex : null;
		$prevToken = $startIndex > 0 ? max(0, $startIndex - $requestedCount) : null;

		// check that all video ids are unique, if not, we have a cache issue, re-cache:
		$videoIds = array_map(fn($video) => $video['id'], $allVideos);
		if (count($videoIds) !== count(array_unique($videoIds))) {
			// re-cache all pages
			$cache->clear();
			return $this->getPopularVideos($countryCode, $pageToken, $maxResults);
		}
		// dd($allVideos, $videosSlice);

		return [
			'videos' => $videosSlice,
			'errors' => $cachedPage['error'] ?? null,
			'nextToken' => $nextToken,
			'prevToken' => $prevToken,
			'offset' => $startIndex,
			'youTubeNextToken' => $youTubeNextToken,
			'youTubePrevToken' => $youTubePrevToken,
			'totalResults' => $totalResults,
			'numResults' => count($videosSlice),
			'oldest_cached_page' => $cachedAt,
			'lastPageNumber' => $lastPageNumber,
			'firstPageNumber' => $firstPageNumber,
			'dispatchedPrefetcher' => $dispatched,
			'numFreshPages' => $freshPages,
		];
	}

	protected function processApiResponse(array $data, $cacheTime): array {
		$videos = [];

		foreach ($data['items'] ?? [] as $item) {
			$snippet = $item['snippet'] ?? [];
			$videos[$item['id']] = [
				'title' => $snippet['title'] ?? '',
				'description' => $snippet['description'] ?? '',
				'publishedAt' => $snippet['publishedAt'] ?? '',
				'thumbnails' => [
					'default' => $snippet['thumbnails']['default']['url'] ?? '',
					'medium' => $snippet['thumbnails']['medium']['url'] ?? '',
					'high' => $snippet['thumbnails']['high']['url'] ?? '',
				],
				'id' => $item['id'] ?? '',
			];
		}

		$numericTokenForNextPage = $this->tokenService->getOffsetFromNextToken($data['nextPageToken'] ?? null);
		$numericTokenForPrevPage = isset($data['prevPageToken'])
			? $this->tokenService->getOffsetFromPrevToken($data['prevPageToken'])
			: null;

		// either token could be null, offset needs at least one to be set
		$offset = $numericTokenForNextPage ? $numericTokenForNextPage - self::MAX_RESULTS : null;
		$offset = $numericTokenForPrevPage ? $numericTokenForPrevPage + self::MAX_RESULTS : $offset;

		return [
			'videos' => $videos,
			'nextToken' => $numericTokenForNextPage,
			'prevToken' => $numericTokenForPrevPage,
			'offset' => $offset,
			'youTubeNextToken' => $data['nextPageToken'] ?? null, // YouTube's next page token
			'youTubePrevToken' => $data['prevPageToken'] ?? null, // YouTube's previous page token
			'totalResults' => $data['pageInfo']['totalResults'] ?? 0,
			'numResults' => count($videos),
			'cachedAt' => $cacheTime,
		];
	}
}
