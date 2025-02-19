<?php

namespace App\Providers;

use App\Clients\WikipediaClient;
use App\Clients\YouTubeClient;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider {
	/**
	 * Register any application services.
	 */
	public function register(): void {
		//
		$this->app->singleton(WikipediaClient::class, function ($app) {
			return new WikipediaClient(
				$app->make('Illuminate\Http\Client\Factory'),
				$app->make('Illuminate\Contracts\Cache\Repository'),
				// $app->make('cache')->store('redis'),
			);
		});

		$this->app->alias(WikipediaClient::class, 'wikipedia');

		// also for YouTubeClient

		$this->app->singleton(YouTubeClient::class, function ($app) {
			return new YouTubeClient(
				$app->make('Illuminate\Http\Client\Factory'),
				$app->make('Illuminate\Contracts\Cache\Repository'),
				// $app->make('cache')->store('redis'),
				$app->make('App\Services\TokenService'),
			);
		});

		$this->app->alias(YouTubeClient::class, 'youtube');
	}
	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void {
		//
	}
}
