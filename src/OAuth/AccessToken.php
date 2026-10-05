<?php

namespace FLAIRUK\Uber\OAuth;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * An OAuth token from auth.uber.com. Store toArray() and rebuild it with fromArray().
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AccessToken implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?DateTimeImmutable $expiresAt = null,
        public array $scopes = [],
        public string $tokenType = 'Bearer',
    ) {}

    /**
     * From a token endpoint response, or a stored toArray().
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $expiresAt = match (true) {
            isset($data['expires_at']) => new DateTimeImmutable('@'.(int) $data['expires_at']),
            isset($data['expires_in']) => new DateTimeImmutable('@'.(time() + (int) $data['expires_in'])),
            default => null,
        };

        $scopes = $data['scopes'] ?? $data['scope'] ?? [];

        return new self(
            accessToken: (string) $data['access_token'],
            refreshToken: isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            expiresAt: $expiresAt,
            scopes: is_array($scopes) ? array_values($scopes) : array_values(array_filter(explode(' ', (string) $scopes))),
            tokenType: (string) ($data['token_type'] ?? 'Bearer'),
        );
    }

    /**
     * Whether the token has expired, or will within $leeway seconds.
     */
    public function isExpired(int $leeway = 60): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->getTimestamp() - $leeway <= time();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_at: ?int, scopes: list<string>, token_type: string}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt?->getTimestamp(),
            'scopes' => $this->scopes,
            'token_type' => $this->tokenType,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->accessToken;
    }
}
