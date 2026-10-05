<?php

namespace FLAIRUK\Uber\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'uber:install')]
class InstallCommand extends Command
{
    protected $signature = 'uber:install';

    protected $description = 'Publish the Uber config and add its environment variables to .env';

    /** @var array<string, string> */
    protected array $variables = [
        'UBER_CLIENT_ID' => '',
        'UBER_CLIENT_SECRET' => '',
        'UBER_SANDBOX' => 'true',
        'UBER_REDIRECT_URI' => '',
        'UBER_DIRECT_CUSTOMER_ID' => '',
        'UBER_ORGANIZATION_ID' => '',
        'UBER_WEBHOOK_SIGNING_KEY' => '',
    ];

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', ['--tag' => 'uber-config']);

        foreach ([$this->laravel->environmentFilePath(), base_path('.env.example')] as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $contents = $files->get($path);
            $missing = array_filter(
                $this->variables,
                fn (string $key) => ! preg_match("/^{$key}=/m", $contents),
                ARRAY_FILTER_USE_KEY,
            );

            if ($missing) {
                $lines = array_map(fn ($key, $value) => "{$key}={$value}", array_keys($missing), $missing);
                $files->append($path, PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL);
                $this->components->info('Added '.implode(', ', array_keys($missing)).' to '.basename($path).'.');
            }
        }

        $this->components->info('Add your client id and secret, then run `php artisan uber:status --scope=<scope>` to check them.');

        return self::SUCCESS;
    }
}
