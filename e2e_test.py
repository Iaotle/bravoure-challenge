#!/usr/bin/env python
import time
import unittest
import requests

APP_URL = "http://127.0.0.1:9000"

class CountriesPaginationTest(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        # seed the list of countries
        seed_full_countries_url = f"{APP_URL}/seed-countries"
        resp = requests.get(seed_full_countries_url)

    def setUp(self):
        """Clear the cache before each test."""
        clear_url = f"{APP_URL}/clear-cache"
        resp = requests.get(clear_url)
        self.assertEqual(resp.status_code, 200, "Request failed")
            
    @classmethod
    def tearDownClass(cls):
        # seed with CountrySeeder to reset the database
        seed_countries_url = f"{APP_URL}/seed-countries"
        resp = requests.get(seed_countries_url)
        
    def test_cache(self):
        print("Testing cache")
        # Fetch all the results into memory to test the cache
        uncachedTime = time.time()
        resp = requests.get(f"{APP_URL}/countries", params={"maxResults": 1000})
        uncachedEndTime = time.time()
        self.assertEqual(resp.status_code, 200, "Request failed")
        
        uncachedTimeTaken = uncachedEndTime - uncachedTime
        print("Time taken for getting uncached results: ", uncachedTimeTaken)
        
        
        
        startTime = time.time()
        # now that we fetched all the results into memory, we can test the cache. This test should take less than 10 seconds
        video_ids_seen = set()
        total_videos_expected = None

        url = f"{APP_URL}/countries"
        params = {
            "maxResults": 1000,  # Testing with a batch of 10 for efficiency
        }
        resp = requests.get(url, params=params)
        endTime = time.time()
        self.assertEqual(resp.status_code, 200,
                        f"Request failed")
        
        # make sure that the cache is working (responses should be faster)
        self.assertLess(endTime - startTime, uncachedTimeTaken, "Cache is not faster")
        

        json_data = resp.json()
        self.assertIn("data", json_data, "Response JSON missing 'data' key.")
        self.assertIsInstance(json_data["data"], list,
                            "'data' should be a list.")
        self.assertGreaterEqual(len(json_data["data"]), 1,
                                "Expected at least one country entry in 'data'.")

        country_data = json_data["data"]
        for country in country_data:
            country_code = country.get("country")

            # Verify pagination offset matches the requested pageToken
            self.assertEqual(country.get("offset"), 0,
                            f"Expected offset 0 but got {country.get('offset')}.")

            # 'numResults' should match the number of videos returned
            videos = country.get("videos", {})
            num_results = country.get("numResults")
            self.assertEqual(len(videos), num_results,
                            f"numResults ({num_results}) does not match count of videos ({len(videos)}) for country={country_code}.")

            # Capture total expected results if provided
            if total_videos_expected is None:
                total_videos_expected = country.get("totalResults")
            if total_videos_expected == 0:
                break

            # Check video uniqueness
            for vid in videos.keys():
                self.assertNotIn(vid, video_ids_seen,
                                f"Duplicate video id '{vid}' found for country={country_code}.")
                video_ids_seen.add(vid)

        # Validate the total number of unique videos
        if total_videos_expected is not None:
            self.assertEqual(len(video_ids_seen), total_videos_expected,
                            f"Total unique videos ({len(video_ids_seen)}) does not match expected totalResults ({total_videos_expected}) for country={country_code}.")
        print("Time taken for getting cached results: ", endTime - startTime)
        

    def test_pagination_and_unique_videos(self):
        """Test pagination and uniqueness of videos for all supported countries."""
        # go back to last character:
        print("\n\033[F", end="")
        max_results = 50
        # Get the list of supported countries
        countries_url = f"{APP_URL}/supported-countries"
        resp = requests.get(countries_url)
        self.assertEqual(resp.status_code, 200, "Failed to fetch supported countries.")
        
        self.supported_countries = resp.json()
        self.assertIsInstance(self.supported_countries, dict, "Expected a dictionary from /supported-countries.")
        
        for country_code in self.supported_countries.keys():
            with self.subTest(country=country_code):
                video_ids_seen = set()
                page_token = 0
                total_videos_expected = None
                while True:
                    # log here
                    print(f"Fetching page {page_token} for country={country_code}")
                    url = f"{APP_URL}/countries"
                    params = {
                        "pageToken": page_token,
                        "maxResults": max_results,  # Testing with a batch of 10 for efficiency
                        "country": country_code
                    }
                    resp = requests.get(url, params=params)
                    self.assertEqual(resp.status_code, 200,
                                    f"Request failed for country={country_code}, pageToken={page_token}")

                    json_data = resp.json()
                    self.assertIn("data", json_data, "Response JSON missing 'data' key.")
                    self.assertIsInstance(json_data["data"], list,
                                        "'data' should be a list.")
                    self.assertGreaterEqual(len(json_data["data"]), 1,
                                            "Expected at least one country entry in 'data'.")

                    country_data = json_data["data"][0]

                    # Verify pagination offset matches the requested pageToken
                    self.assertEqual(country_data.get("offset"), page_token,
                                    f"Expected offset {page_token} but got {country_data.get('offset')}.")

                    # 'numResults' should match the number of videos returned
                    videos = country_data.get("videos", {})
                    num_results = country_data.get("numResults")
                    self.assertEqual(len(videos), num_results,
                                    f"numResults ({num_results}) does not match count of videos ({len(videos)}) for country={country_code}, pageToken={page_token}.")

                    # Capture total expected results if provided
                    if total_videos_expected is None:
                        total_videos_expected = country_data.get("totalResults")
                    if total_videos_expected == 0:
                        break

                    # Check video uniqueness
                    for vid in videos.keys():
                        self.assertNotIn(vid, video_ids_seen,
                                        f"Duplicate video id '{vid}' found for country={country_code}, pageToken={page_token}.")
                        video_ids_seen.add(vid)

                    # Check pagination
                    next_token = country_data.get("nextToken")
                    if next_token is None:
                        break  # No more pages

                    self.assertEqual(next_token, page_token + num_results,
                                    f"Expected nextToken to be {page_token + num_results} but got {next_token}.")

                    page_token = next_token  # Move to the next page

                # Validate the total number of unique videos
                if total_videos_expected is not None:
                    self.assertEqual(len(video_ids_seen), total_videos_expected,
                                    f"Total unique videos ({len(video_ids_seen)}) does not match expected totalResults ({total_videos_expected}) for country={country_code}.")
    
    def test_prefetch_dispatched(self):
        # test that the API replies with dispatchedPrefetcher: true once for each country
        url = f"{APP_URL}/countries"
        params = {
            "maxResults": 10
        }
        resp = requests.get(url, params=params)
        self.assertEqual(resp.status_code, 200, "Request failed")
        json_data = resp.json()
        self.assertIn("data", json_data, "Response JSON missing 'data' key.")
        self.assertIsInstance(json_data["data"], list,
                        "'data' should be a list.")
        self.assertGreaterEqual(len(json_data["data"]), 1,
                            "Expected at least one country entry in 'data'.")
        country_data = json_data["data"]
        for country in country_data:
            # only relevant if we have results
            if country.get("videos"):
                self.assertTrue(country.get("dispatchedPrefetcher"), "dispatchedPrefetcher should be true")
        
        
        # and next time it isn't:
        resp = requests.get(url, params=params)
        self.assertEqual(resp.status_code, 200, "Request failed")
        json_data = resp.json()
        self.assertIn("data", json_data, "Response JSON missing 'data' key.")
        self.assertIsInstance(json_data["data"], list,
                        "'data' should be a list.")
        self.assertGreaterEqual(len(json_data["data"]), 1,
                            "Expected at least one country entry in 'data'.")
        country_data = json_data["data"]
        for country in country_data:
            self.assertFalse(country.get("dispatchedPrefetcher"), "dispatchedPrefetcher should be false")
    
        

if __name__ == "__main__":
    unittest.main()
