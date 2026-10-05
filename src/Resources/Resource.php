<?php

namespace FLAIRUK\Uber\Resources;

use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

abstract class Resource
{
    /**
     * The app-token scopes this resource's endpoints need when there is no user token.
     *
     * @var list<string>
     */
    protected array $scopes = [];

    /**
     * Which API this is, for its sandbox host: rides, guests, direct, eats or business.
     */
    protected string $api = 'rides';

    public function __construct(protected Uber $client) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>|null  $scopes
     */
    protected function get(string $path, array $query = [], ?array $scopes = null): Response
    {
        return $this->send('GET', $path, ['query' => $query], $scopes);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  list<string>|null  $scopes
     * @param  bool  $retry  only for idempotent POSTs such as estimates and quotes
     */
    protected function post(string $path, array $body = [], ?array $scopes = null, bool $retry = false): Response
    {
        return $this->send('POST', $path, ['json' => $body], $scopes, $retry);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  list<string>|null  $scopes
     */
    protected function put(string $path, array $body = [], ?array $scopes = null): Response
    {
        return $this->send('PUT', $path, ['json' => $body], $scopes, false);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  list<string>|null  $scopes
     */
    protected function patch(string $path, array $body = [], ?array $scopes = null): Response
    {
        return $this->send('PATCH', $path, ['json' => $body], $scopes, false);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  list<string>|null  $scopes
     */
    protected function delete(string $path, array $body = [], ?array $scopes = null): Response
    {
        return $this->send('DELETE', $path, $body === [] ? [] : ['json' => $body], $scopes, false);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>|null  $scopes
     */
    protected function send(string $method, string $path, array $options, ?array $scopes = null, ?bool $retry = null): Response
    {
        $options['api'] = $this->api;
        $options['headers'] = $this->headers() + ($options['headers'] ?? []);

        return $this->client->send($method, $path, $options, $scopes ?? $this->scopes, $retry);
    }

    /**
     * Headers every request from this resource carries.
     *
     * @return array<string, string|null>
     */
    protected function headers(): array
    {
        return [];
    }

    /**
     * URL-encode one path segment, e.g. an id that came from user input.
     */
    protected function segment(string|int $value): string
    {
        return rawurlencode((string) $value);
    }

    /**
     * Offset/limit pagination: the next page starts where this one ended.
     *
     * @param  array<string, mixed>  $query
     */
    protected function offsetPaginated(string $path, array $query, string $itemsKey, int $limit, ?array $scopes = null): Response
    {
        $query['limit'] ??= $limit;
        $query['offset'] ??= 0;

        $response = $this->get($path, $query, $scopes);

        return $response->paginate($itemsKey, function (Response $page) use ($path, $query, $itemsKey, $scopes) {
            $count = count($page->items());
            $total = $page->get('count');

            if ($count === 0 || $count < $query['limit'] || (is_numeric($total) && (int) $total <= $query['offset'] + $count)) {
                return null;
            }

            return $this->offsetPaginated($path, ['offset' => $query['offset'] + $count] + $query, $itemsKey, $query['limit'], $scopes);
        });
    }
}
