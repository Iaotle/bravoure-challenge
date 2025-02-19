<?php

namespace App\Clients;

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

	public function __construct(private HttpClient $http, private CacheRepository $cache, private $tokenService) {
		$this->apiKey = config('services.youtube.api_key');
		$this->apiUrl = config('services.youtube.api_url', 'https://www.googleapis.com/youtube/v3/videos');
		$this->cacheDuration = config('services.youtube.cache_duration', 60 * 24); // in minutes
	}

	public function getPopularVideos(string $countryCode, ?int $pageToken = 0, ?int $maxResults = self::MAX_RESULTS): array {
		$this->validateCountryCode($countryCode);

		// Interpret $pageToken as the starting index and $maxResults as the ending index.
		// For example, pageToken=10 and maxResults=20 means “give me videos 10 through 29” (20 videos).
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

		// Loop over each needed page.
		$cachedAt = now();
		for ($page = $firstPageNumber; $page <= $lastPageNumber; $page++) {
			// We use our helper "mapLineToToken" to get the YouTube API page token.
			$lineNumber = $page * self::MAX_RESULTS;
			$youTubePageToken = $this->tokenService->getTokenFromOffset($lineNumber);
			$tags = ['youtube', 'country-' . strtolower($countryCode)];

			$cacheKey = "youtube_{strtolower($countryCode)}_{strtolower($youTubePageToken)}";
			$cache = $this->cache;

			if ($cache->supportsTags()) {
				$cache->tags($tags);
			}
			$cachedPage = $cache->remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use (
				$countryCode,
				$youTubePageToken,
			) {
				$params = [
					'chart' => 'mostPopular',
					'regionCode' => strtoupper($countryCode),
					'part' => 'snippet',
					'maxResults' => self::MAX_RESULTS, // always 50 per API call
					'key' => $this->apiKey,
					'pageToken' => $youTubePageToken,
				];

				$response = $this->http->get($this->apiUrl, $params);
				if ($response->failed()) {
					Log::error('YouTube API request failed', ['response' => $response->body()]);
					return [
						'videos' => [],
						'nextToken' => null,
						'prevToken' => null,
						'totalResults' => 0,
						'cached_at' => null,
						'numResults' => 0,
						'youTubeNextToken' => null,
						'youTubePrevToken' => null,
					];
				}
				return $this->processApiResponse($response->json());
			});

			// For the first fetched page record total available results and YouTube’s API tokens.
			if (empty($allVideos)) {
				$totalResults = $cachedPage['totalResults'];
				$youTubeNextToken = $cachedPage['youTubeNextToken'];
				$youTubePrevToken = $cachedPage['youTubePrevToken'];
				// cap the last page number based on the total results to avoid fetching empty pages
				$lastPageNumber = min($lastPageNumber, (int) ceil($totalResults / self::MAX_RESULTS));
			}
			// get cached_at timestamp
			$when_cached = $cachedPage['cached_at'] ?? null;
			// get the oldest cached_at timestamp
			$cachedAt = $when_cached && $when_cached < $cachedAt ? $when_cached : $cachedAt;
			// Append the videos from this cached page.
			$allVideos = array_merge($allVideos, $cachedPage['videos']);
		}

		// Our combined $allVideos covers from index ($firstPageNumber * 50)
		// Calculate the offset into the first fetched page:
		$offsetWithinPage = $startIndex - $firstPageNumber * $pageSize;
		$videosSlice = array_slice($allVideos, $offsetWithinPage, $requestedCount);

		// Compute new pagination tokens based on the indices.
		$nextToken = $endIndex < $totalResults ? $endIndex : null;
		$prevToken = $startIndex > 0 ? max(0, $startIndex - $requestedCount) : null;

		return [
			'videos' => $videosSlice,
			'nextToken' => $nextToken,
			'prevToken' => $prevToken,
			'offset' => $startIndex,
			'youTubeNextToken' => $youTubeNextToken,
			'youTubePrevToken' => $youTubePrevToken,
			'totalResults' => $totalResults,
			'numResults' => count($videosSlice),
			'oldest_cached_page' => $cachedAt,
			'lastPageNumber' => $lastPageNumber,
		];
	}

	protected function validateCountryCode(string $countryCode): void {
		$validator = Validator::make(['country_code' => $countryCode], ['country_code' => 'required|string|size:2|alpha']);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}
	}

	protected function processApiResponse(array $data): array {
		$videos = [];

		foreach ($data['items'] ?? [] as $item) {
			$snippet = $item['snippet'] ?? [];
			$videos[] = [
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
			'cached_at' => now(),
		];
	}
}
