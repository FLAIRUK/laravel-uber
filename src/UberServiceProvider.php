<?php

namespace FLAIRUK\Uber;

use FLAIRUK\Uber\Http\Controllers\WebhookController;
use FLAIRUK\Uber\Http\Middleware\VerifyWebhookSignature;
use FLAIRUK\Uber\OAuth\OAuth;
use FLAIRUK\Uber\Webhooks\WebhookSignature;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class UberServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/uber.php', 'uber');

        $this->app->singleton(OAuth::class, fn (Application $app) => new OAuth(
            $app->make(Http::class),
            $app['cache']->store($app['config']->get('uber.cache_store')),
            $app['config']->get('uber'),
        ));

        $this->app->singleton(Uber::class, fn (Application $app) => new Uber(
            $app->make(Http::class),
            $app->make(OAuth::class),
            $app['config']->get('uber'),
        ));

        $this->app->alias(Uber::class, 'uber');

        $this->app->bind(WebhookSignature::class, fn (Application $app) => new WebhookSignature([
            ...(array) $app['config']->get('uber.webhooks.signing_keys', []),
            (string) $app['config']->get('uber.client_secret'),
        ]));
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('uber.webhook', VerifyWebhookSignature::class);

        if (filled($path = $this->app['config']->get('uber.webhooks.path')) && ! $this->app->routesAreCached()) {
            $router->post($path, WebhookController::class)
                ->middleware(VerifyWebhookSignature::class)
                ->name('uber.webhook');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/uber.php' => config_path('uber.php'),
        ], 'uber-config');

        $this->commands([
            Console\InstallCommand::class,
            Console\StatusCommand::class,
        ]);
    }
}
