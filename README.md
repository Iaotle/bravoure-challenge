# YouTube Prefetcher Job & API Integration

## Overview
The project includes a Laravel job (`Prefetcher`) and a YouTube API client (`YouTubeClient`) that efficiently fetches and caches popular videos. It also includes a `WikipediaClient` for fetching country descriptions and various routes for cache management and data seeding.

Some things are left as TODOs.

## Feature highlights
- I wanted to use `Job::dispatchAfterResponse()` to dispatch the job without interfering with the current process, but that requires FastCGI
- Handles caching consistency.
- Ensures unique video IDs to prevent caching issues.
- Handles being rate-limited by returning nothing

## Country API
- `/countries`: Main endpoint, returns
- `/supported-countries`: Fetches country names and ISO codes.

## Cache Management Routes
- `/clear-cache`: Clears all caches.
- `/clear-country-cache/{country}`: Clears YouTube and Wikipedia caches for a specific country.
- `/clear-youtube-cache`: Clears only the YouTube cache.
- `/clear-wikipedia-cache`: Clears only the Wikipedia cache.
- `/clear-country-description`: Clears stored country descriptions in the database.

## Data Seeding Routes
- `/seed-countries`: Runs `CountrySeeder`.
- `/seed-full-countries`: Runs `FullCountrySeeder`.


## Prefetcher Job
- Implements Laravel's `ShouldQueue` for background execution.
- Uses a next-page token to prefetch and cache YouTube videos.
- Gets dispatched to fetch a full list of videos for a country.
- Logs errors when fetching fails.

## YouTubeClient
- Fetches and caches popular YouTube videos.
- Implements caching with Redis, avoiding duplicate API requests while the cache is fresh.
- Dispatches the `Prefetcher` job for continuous prefetching.

## TokenService
- Converts YouTube's next-page tokens to numeric offsets for better pagination.
- Keeps a list of tokens in cache.


## WikipediaClient
- Retrieves country descriptions from Wikipedia.
- Caches responses for 24 hours to minimize API calls.
- Falls back to the database if a description is already stored.
- Handles API errors and logs failures.
