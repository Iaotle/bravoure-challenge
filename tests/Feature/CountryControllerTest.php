<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Country;
use App\Clients\WikipediaClient;
use App\Clients\YouTubeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CountryControllerTest extends TestCase {
	/**
	 * Test that when requesting country data with pagination (maxResults=5 and a pageToken),
	 * the YouTubeClient only makes one call per country already in the DB.
	 */
	public function test_youtube_backend_is_called_only_once_per_country_with_max_results_5_and_pagination() {
		// run the country seeder to populate the database
		// $this->artisan('db:seed --class=FullCountrySeeder');

		// Determine how many countries we have.
		$countryCodes = Country::pluck('iso_alpha_2')->toArray();
		$expectedCallCount = count($countryCodes);

		// Create a mock for the YouTubeClient.
		$youtubeClientMock = $this->createMock(YouTubeClient::class);

		// Expect getPopularVideos() to be called exactly once per country.
		// For this test, we pass a dummy pageToken ("150") and maxResults=5.
		$matcher = $this->exactly($expectedCallCount);

		$youtubeClientMock
			->expects($matcher)
			->method('getPopularVideos')
			->willReturnCallback(function ($country, $token, $maxResults) use ($matcher, $countryCodes) {
				$expectedIndex = $matcher->numberOfInvocations() - 1;
				$this->assertSame($countryCodes[$expectedIndex], $country);
				$this->assertSame(150, $token);
				$this->assertSame(5, $maxResults);

				return []; // Return an empty array or a dummy payload.
			});

		// Stub the WikipediaClient so that we don't depend on its external API.
		$wikipediaClientStub = $this->createStub(WikipediaClient::class);
		$wikipediaClientStub->method('getExtract')->willReturn('Sample extract');

		// Bind our mocks into the service container.
		$this->app->instance(YouTubeClient::class, $youtubeClientMock);
		$this->app->instance(WikipediaClient::class, $wikipediaClientStub);

		// Perform a GET request with pagination parameters.
		// (Make sure your test route matches the route defined in your application.)
		$response = $this->get('/countries?maxResults=5&pageToken=150');

		// Assert that the response is OK.
		$response->assertStatus(200);

		// migrate back to the original state
		$this->artisan('db:seed --class=CountrySeeder');
	}

	public function test_fetching_all_pages_for_single_country_makes_ceil_total_results_over_50_requests() {
		Cache::flush();
		// Total results and expected API calls
		$totalResults = 120;
		$expectedApiCalls = (int) ceil($totalResults / 50); // 3

		// Mock YouTube API responses with 50, 50, and 20 videos
		Http::fake([
			'www.googleapis.com/youtube/v3/videos*' => Http::sequence()
				->push($this->mockYouTubeResponse(50, 50, $totalResults))
				->push($this->mockYouTubeResponse(50, 100, $totalResults))
				->push($this->mockYouTubeResponse(20, null, $totalResults)),
		]);

		// Stub WikipediaClient to avoid external calls
		$wikipediaStub = $this->createStub(WikipediaClient::class);
		$wikipediaStub->method('getExtract')->willReturn('Sample extract');
		$this->app->instance(WikipediaClient::class, $wikipediaStub);

		// Simulate paginated requests with maxResults=5
		$nextToken = 0;
		$requestCount = 0;
		do {
			$response = $this->get("/countries?maxResults=5&country=IT&pageToken=$nextToken");
			$response->assertStatus(200);
			$data = $response->json();
			$nextToken = $data['data'][0]['nextToken'] ?? null;
			$requestCount++;
		} while ($nextToken !== null);

		// Assert the number of HTTP requests matches expected API calls
		Http::assertSentCount($expectedApiCalls);
	}

	private function mockYouTubeResponse(int $videosCount, ?string $nextPageToken, int $totalResults): array {
		// Determine starting index based on the page token (assuming it's numeric)
		$startIndex = $nextPageToken ? (int) $nextPageToken * $videosCount : 0;

		return [
			'items' => array_map(function ($index) use ($startIndex) {
				return [
					'id' => 'dummyId_' . ($startIndex + $index),
					'snippet' => [
						'title' => 'Dummy Title ' . ($startIndex + $index),
						'description' => 'Dummy Description',
						'publishedAt' => '2023-01-01T00:00:00Z',
						'thumbnails' => [
							'default' => ['url' => 'http://example.com/default.jpg'],
							'medium' => ['url' => 'http://example.com/medium.jpg'],
							'high' => ['url' => 'http://example.com/high.jpg'],
						],
					],
				];
			}, range(0, $videosCount - 1)),

			'nextToken' => $nextPageToken !== null ? (string) ((int) $nextPageToken + 1) : null,

			'pageInfo' => [
				'totalResults' => $totalResults,
			],
		];
	}

	public function test_using_mock_token_service() {
		// Create a simple stub for the TokenService.
		$tokenServiceStub = new class {
			public function getTokenFromOffset($offset) {
				return "token_{$offset}";
			}
			public function getOffsetFromNextToken($token) {
				if (!$token) {
					return null;
				}
				// e.g., "token_0" -> offset 50, "token_50" -> offset 100, etc.
				$offset = (int) str_replace('token_', '', $token);
				return $offset + \App\Clients\YouTubeClient::MAX_RESULTS;
			}
			public function getOffsetFromPrevToken($token) {
				if (!$token) {
					return null;
				}
				$offset = (int) str_replace('token_', '', $token);
				return max(0, $offset - \App\Clients\YouTubeClient::MAX_RESULTS);
			}
		};

		// Counter for how many times the HTTP client is called.
		$httpCallCount = 0;

		// Use Mockery to create a mock for the HttpClient.
		$httpClientMock = \Mockery::mock(\Illuminate\Http\Client\Factory::class);
		
		$httpClientMock->shouldReceive('get')->andReturnUsing(function ($url, $params) use (&$httpCallCount) {
			$httpCallCount++;

			// Simulate a full page of videos (50 videos per page).
			$videos = array_map(function ($index) {
				return [
					'snippet' => [
						'title' => 'Dummy Video ' . ($index + 1),
						'description' => 'Dummy Description',
						'publishedAt' => now()->toDateTimeString(),
						'thumbnails' => [
							'default' => ['url' => 'default.jpg'],
							'medium' => ['url' => 'medium.jpg'],
							'high' => ['url' => 'high.jpg'],
						],
					],
					'id' => 'dummy_id_' . $index,
				];
			}, range(0, \App\Clients\YouTubeClient::MAX_RESULTS - 1));

			// Determine the nextPageToken based on the current token.
			$currentToken = $params['pageToken'];
			$nextToken = null;
			if ($currentToken === 'token_0') {
				$nextToken = 'token_50';
			} elseif ($currentToken === 'token_50') {
				$nextToken = 'token_100';
			} elseif ($currentToken === 'token_100') {
				$nextToken = null;
			}

			// Return a dummy response object.
			return new class ($videos, $nextToken) {
				private $videos;
				private $nextToken;
				public function __construct($videos, $nextToken) {
					$this->videos = $videos;
					$this->nextToken = $nextToken;
				}
				public function failed() {
					return false;
				}
				public function json() {
					return [
						'items' => $this->videos,
						'nextPageToken' => $this->nextToken,
						'prevPageToken' => null,
						'pageInfo' => ['totalResults' => 120],
					];
				}
				public function body() {
					return '';
				}
			};
		});

		// Use an in-memory cache so that fetched pages are reused.
		$cacheStore = new \Illuminate\Cache\ArrayStore();
		$cacheRepository = new \Illuminate\Cache\Repository($cacheStore);

		// Instantiate the YouTubeClient with the mocked HttpClient, cache, and TokenService stub.
		$youtubeClient = new \App\Clients\YouTubeClient($httpClientMock, $cacheRepository, $tokenServiceStub);

		$countryCode = 'US';

		// Simulate many paginated requests in increments of 5 (covering 120 total videos).
		for ($pageToken = 0; $pageToken < 120; $pageToken += 5) {
			$result = $youtubeClient->getPopularVideos($countryCode, $pageToken, 5);
			// Verify that the returned slice never exceeds 5 videos.
			$this->assertLessThanOrEqual(5, $result['numResults']);
		}

		// Even though we called getPopularVideos() many times,
		// only ceil(120 / 50) = 3 unique API requests (cached pages) should be made.
		$this->assertEquals(3, $httpCallCount);

		\Mockery::close();
	}

	// clear cache, otherwise we have dummies in the main cache
	protected function tearDown(): void {
		Cache::flush();
		parent::tearDown();
	}
}
