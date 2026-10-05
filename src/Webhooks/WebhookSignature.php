<?php

namespace FLAIRUK\Uber\Webhooks;

use FLAIRUK\Uber\Exceptions\ConfigurationException;

/**
 * Uber's webhook signature: hex HMAC-SHA256 of the raw body, in X-Uber-Signature
 * (Uber Direct also sends X-Postmates-Signature).
 *
 * The key depends on the API: Riders, Eats and Vehicle Suppliers sign with your
 * client secret; Guest Rides, Health, Business and Direct with the signing key shown
 * for each webhook in the dashboard. A signature is accepted if any configured key matches.
 *
 * @see https://developer.uber.com/docs/riders/guides/webhooks
 */
final readonly class WebhookSignature
{
    public const HEADERS = ['X-Uber-Signature', 'X-Postmates-Signature'];

    /**
     * @param  list<string>  $keys
     */
    public function __construct(private array $keys) {}

    /**
     * The signature Uber would send for this body, with the first key.
     */
    public function sign(string $body, ?string $key = null): string
    {
        return hash_hmac('sha256', $body, $key ?? $this->keys()[0]);
    }

    /**
     * Whether the signature matches the body under any configured key, compared in constant time.
     *
     * @throws ConfigurationException if no key is configured
     */
    public function verify(string $body, ?string $signature): bool
    {
        $keys = $this->keys();

        if ($body === '' || blank($signature)) {
            return false;
        }

        $signature = strtolower(trim((string) $signature));

        foreach ($keys as $key) {
            if (hash_equals(hash_hmac('sha256', $body, $key), $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return non-empty-list<string>
     */
    private function keys(): array
    {
        $keys = array_values(array_filter($this->keys, fn ($key) => is_string($key) && $key !== ''));

        return $keys !== []
            ? $keys
            : throw new ConfigurationException('No Uber webhook key: set UBER_WEBHOOK_SIGNING_KEY or UBER_CLIENT_SECRET.');
    }
}
