<?php

namespace FLAIRUK\Uber\Console;

use FLAIRUK\Uber\Exceptions\UberException;
use FLAIRUK\Uber\Uber;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'uber:status')]
class StatusCommand extends Command
{
    protected $signature = 'uber:status
        {--scope=* : An app scope to request a token for, e.g. eats.deliveries (repeatable)}';

    protected $description = 'Check the Uber client credentials by requesting an app token';

    public function handle(Uber $uber): int
    {
        $scopes = (array) $this->option('scope');

        $this->components->twoColumnDetail('API', $uber->baseUrl());
        $this->components->twoColumnDetail('OAuth', $uber->oauth()->authUrl());
        $this->components->twoColumnDetail('Environment', $uber->isSandbox() ? 'sandbox' : 'production');

        if ($scopes === []) {
            $this->components->warn('Pass --scope with a scope your app is approved for (e.g. --scope=eats.deliveries) to test the credentials.');

            return self::SUCCESS;
        }

        try {
            $uber->oauth()->forgetClientToken($scopes);
            $token = $uber->oauth()->clientToken($scopes);
        } catch (UberException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Uber issued an app token.');
        $this->components->twoColumnDetail('Scopes', implode(' ', $token->scopes ?: $scopes));
        $this->components->twoColumnDetail('Expires', $token->expiresAt?->format(DATE_RFC2822) ?? 'unknown');

        return self::SUCCESS;
    }
}
