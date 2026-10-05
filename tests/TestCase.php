<?php

namespace FLAIRUK\Uber\Tests;

use FLAIRUK\Uber\Facades\Uber;
use FLAIRUK\Uber\OAuth\OAuth;
use FLAIRUK\Uber\UberServiceProvider;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const BASE = 'https://api.uber.com';

    protected const TOKEN_URL = 'https://auth.uber.com/oauth/v2/token';

    protected function getPackageProviders($app): array
    {
        return [UberServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Uber' => Uber::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('uber.client_id', 'test-client');
        $app['config']->set('uber.client_secret', 'test-secret');
        $app['config']->set('uber.direct.customer_id', 'cust-1');
        $app['config']->set('uber.retry', [1, 0]);
    }

    /**
     * Fake Uber, with an app token endpoint that always succeeds.
     *
     * @param  array<string, mixed>  $responses
     */
    protected function fake(array $responses = []): void
    {
        Http::preventStrayRequests();
        Http::fake([self::TOKEN_URL => $this->tokenResponse()] + $responses + ['*' => Http::response([])]);
    }

    protected function tokenResponse(string $token = 'app-token', int $expiresIn = 2592000): PromiseInterface
    {
        return Http::response([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => 'eats.deliveries',
        ]);
    }

    protected function rebuild(array $config): void
    {
        foreach ($config as $key => $value) {
            config(["uber.{$key}" => $value]);
        }

        $this->app->forgetInstance(\FLAIRUK\Uber\Uber::class);
        $this->app->forgetInstance(OAuth::class);
        Uber::clearResolvedInstances();
    }

    /**
     * Requests sent to the API (not to auth.uber.com).
     *
     * @return list<Request>
     */
    protected function apiRequests(): array
    {
        return Http::recorded(fn ($request) => ! str_starts_with($request->url(), 'https://auth.uber.com'))
            ->map(fn (array $pair) => $pair[0])
            ->values()
            ->all();
    }
}
