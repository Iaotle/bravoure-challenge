<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class YouTubeService {
    public static function getPopularVideos(string $countryCode): array {
        // convert the code to ISO 3166-1 alpha-2 (so for example 'uk' becomes 'GB')
        if ($countryCode === 'uk') {
            $countryCode = 'GB';
        }
        return Cache::remember("youtube_{$countryCode}", now()->addHours(24), function () use ($countryCode) {
            $apiKey = config('services.youtube.api_key'); // set in .env and config/services.php
            $url = 'https://www.googleapis.com/youtube/v3/videos';
            $params = [
                'chart'       => 'mostPopular', // Required to get popular videos
                'regionCode'  => strtoupper($countryCode),
                'part'        => 'snippet',
                'maxResults'  => 10,
                'key'         => $apiKey,
            ];

            $response = Http::get($url, $params);
            // dd($response);

            if ($response->failed()) {
                return [];
            }

            $json = $response->json();
            $videos = [];

            if (isset($json['items'])) {
                foreach ($json['items'] as $item) {
                    $snippet = $item['snippet'] ?? [];
                    $videos[] = [
                        'title'       => $snippet['title'] ?? '',
                        'description' => $snippet['description'] ?? '',
                        'publishedAt' => $snippet['publishedAt'] ?? '',
                        'thumbnails'  => [
                            'default' => $snippet['thumbnails']['default']['url'] ?? '',
                            'medium'  => $snippet['thumbnails']['medium']['url'] ?? '',
                            'high'    => $snippet['thumbnails']['high']['url'] ?? '',
                        ],
                    ];
                }
            }

            return $videos;
        });
    }
}
