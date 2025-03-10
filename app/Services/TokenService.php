<?php

namespace App\Services;

use App\Clients\YouTubeClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class TokenService {
	const MAX_RESULTS = YouTubeClient::MAX_RESULTS; // TODO: grab from client
	const CACHE_KEY = 'youtube_pagetokens';

	protected string $filePath;

	// TODO: dependency inject cache ???
	public function __construct(?string $filePath = null) {
		$this->filePath = $filePath ?? config('services.youtube.pagetokens');
	}

	public function getOffsetFromNextToken(?string $token): ?int {
		return $this->getTokenIndex($token);
	}

	public function getOffsetFromPrevToken(?string $token): ?int {
		if (!$token) {
			return null;
		}

		$modifiedToken = substr_replace($token, 'A', -1);
		$index = $this->getTokenIndex($modifiedToken);

		return $index !== null ? $index - self::MAX_RESULTS : null;
	}

	public function getTokenFromOffset(int $offset): ?string {
		$tokens = $this->getTokens();

		if ($offset < 0 || $offset >= count($tokens)) {
			return null;
		}

		return $tokens[$offset];
	}

	protected function getTokenIndex(?string $token): ?int {
		if (!$token) {
			return null;
		}

		$tokens = $this->getTokens();
		$index = array_search($token, $tokens);

		return $index !== false ? $index : null;
	}

	protected function getTokens(): array {
		return Cache::rememberForever(self::CACHE_KEY, function () {
			// no point in caching for a limited time
			return File::exists($this->filePath) ? File::lines($this->filePath)->filter()->values()->toArray() : [];
		});
	}
}
