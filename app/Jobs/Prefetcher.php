<?php

namespace App\Jobs;

use App\Clients\YouTubeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class Prefetcher implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * Create a new job instance.
	 *
	 * @param string|null $youTubeNextToken
	 * @param string      $countryCode
	 */
	public function __construct(protected ?string $youTubeNextToken, protected string $countryCode) {}

	/**
	 * Execute the job.
	 *
	 * @param  \App\Clients\YouTubeClient  $youTubeClient
	 * @return void
	 */
	public function handle(YouTubeClient $youTubeClient) {
		Log::info('Prefetcher job started for country: ' . $this->countryCode);
		// If there is no next page token, nothing to prefetch.
		if (!$this->youTubeNextToken) {
			return;
		}

		// Convert the YouTube next page token into a numeric offset
		// using your token service. Adjust this if your tokenService is not directly accessible.
		$offset = $youTubeClient->tokenService->getOffsetFromNextToken($this->youTubeNextToken);

		if ($offset === null) {
			Log::warning('Prefetcher: Unable to convert next page token to offset. Exiting.');
			return;
		}

		try {
			// Fetch and cache the next page of popular videos.
			// The getPopularVideos method handles caching and returns the API tokens.
			$videosData = $youTubeClient->getPopularVideos($this->countryCode, $offset);

			// If the newly fetched page provides a next token, dispatch another prefetch job.
			if (!empty($videosData['youTubeNextToken'])) {
				// ->delay(now()->addSeconds(1))
				self::dispatch($videosData['youTubeNextToken'], $this->countryCode);
			}
		} catch (\Exception $e) {
			Log::error('Prefetcher job failed: ' . $e->getMessage());
		}
	}
}
