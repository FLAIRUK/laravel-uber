<?php

namespace FLAIRUK\Uber\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    #[Test]
    public function install_publishes_config_and_adds_missing_env_keys_once(): void
    {
        $env = $this->app->environmentFilePath();
        $original = File::exists($env) ? File::get($env) : null;
        File::put($env, "APP_NAME=Test\nUBER_CLIENT_ID=existing\n");

        try {
            $this->artisan('uber:install')->assertSuccessful();
            $this->artisan('uber:install')->assertSuccessful();

            $contents = File::get($env);
            $this->assertSame(1, substr_count($contents, 'UBER_CLIENT_ID='));
            $this->assertSame(1, substr_count($contents, 'UBER_CLIENT_SECRET='));
            $this->assertStringContainsString('UBER_CLIENT_ID=existing', $contents);
            $this->assertStringContainsString('UBER_SANDBOX=true', $contents);
            $this->assertFileExists(config_path('uber.php'));
        } finally {
            $original === null ? File::delete($env) : File::put($env, $original);
            File::delete(config_path('uber.php'));
        }
    }

    #[Test]
    public function status_requests_a_fresh_token_for_the_given_scope(): void
    {
        $this->fake();

        $this->artisan('uber:status', ['--scope' => ['eats.deliveries']])
            ->expectsOutputToContain('Uber issued an app token')
            ->assertSuccessful();

        $this->artisan('uber:status', ['--scope' => ['eats.deliveries']])->assertSuccessful();

        Http::assertSentCount(2);
    }

    #[Test]
    public function status_reports_refused_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);

        $this->artisan('uber:status', ['--scope' => ['eats.deliveries']])
            ->expectsOutputToContain('invalid_client')
            ->assertFailed();
    }

    #[Test]
    public function status_without_a_scope_explains_what_to_pass(): void
    {
        Http::preventStrayRequests();

        $this->artisan('uber:status')
            ->expectsOutputToContain('--scope')
            ->assertSuccessful();
    }
}
