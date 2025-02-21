# Country Info Viewer: Popular YouTube video caching & Wikipedia information retreival

## Overview
The project includes a Laravel job (`Prefetcher`) and a YouTube API client (`YouTubeClient`) that efficiently fetches and caches popular videos. It also includes a `WikipediaClient` for fetching country descriptions and various routes for cache management and data seeding.

Some things are left as TODOs.

## How to deploy
- `./vendor/bin/sail up`
- Should run at port 9000 or APP_PORT.
- Deploys one queue worker (see `supervisord.conf`)
- Run `force_setup_sail.sh` to run some commands to reset/refresh container configuration if something is not working as expected. Will also run artisan tests.

### Local deployment
You will have to change the .env file to use your own redis/db connections.


## Feature highlights
- I wanted to use `Job::dispatchAfterResponse()` to dispatch the job without interfering with the current process, but that requires FastCGI
- Handles cache consistency by prefetching a full list.
- Ensures unique video IDs to prevent duplicates.
- Handles being rate-limited by returning `videos: [], errors: {...}`
- Frontend in vue that displays cached videos, allows for controlling the various params for easy visual testing.


## E2E test in python
- Refreshes the whole cache
- Fetches all pages for all countries and checks that the cache hits are faster
- Checks that dispatcher job was correctly sent on first request
- Run `python3 e2e_test.py`

## PHPUnit tests with mocks
- Don't rely on the YouTube API when testing
- Mock token service, mock http, etc.
- Run `./vendor/bin/sail artisan test` to test it


## Manual testing
- Look in `storage/logs/laravel.log` for some debug outputs
- Set DB/CACHE env variables to database and refresh the container to use a DB explorer like (e.g. phpmyadmin).

# API overview


## Country API
- `/countries`: Main endpoint, returns the wikipedia extract and videos.
- `/supported-countries`: Fetches country names and ISO codes.

## Cache Management Routes
- `/clear-cache`: Clears all caches.
- `/clear-country-cache/{country}`: Clears YouTube and Wikipedia caches for a specific country.
- `/clear-youtube-cache`: Clears only the YouTube cache.
- `/clear-wikipedia-cache`: Clears only the Wikipedia cache.
- `/clear-country-description`: Clears stored country descriptions in the database. If we already cached once from wikipedia, we will never make a request to the API and will instead use the DB

## Data Seeding Routes
- `/seed-countries`: Runs `CountrySeeder`.
- `/seed-full-countries`: Runs `FullCountrySeeder`. Not recommended as it will make a lot of network requests when requesting all countries for example. Also the restcountries API has some timeout problems so this might not be reliable (I put in a retry and timeout to the request and it's worked for me every time since, YMMV).


# Main Laravel components

## Prefetcher Job
- Uses a next-page token to prefetch and cache YouTube videos.
- Gets dispatched to fetch a full list of videos for a country, recursively dispatches more jobs if there are more pages.
- Logs errors when fetching fails.

## YouTubeClient
- Fetches and caches popular YouTube videos.
- Implements caching with Redis, avoiding duplicate API requests while the cache is fresh.
- Dispatches the `Prefetcher` job for continuous prefetching.

## TokenService
- Converts YouTube's next-page tokens to numeric offsets for better pagination.
- Keeps a list of tokens in cache. We have more than enough page tokens.


## WikipediaClient
- Retrieves country descriptions from Wikipedia.
- Use the database if a description is already stored.
- Caches exerpts in redis for 24 hours to minimize API/DB calls.
