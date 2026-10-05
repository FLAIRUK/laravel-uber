<?php

namespace FLAIRUK\Uber\Webhooks;

use Illuminate\Support\Arr;

/**
 * A verified Uber webhook notification.
 *
 * Most APIs send {event_id, event_type, event_time, meta, resource_href}; Uber Direct
 * sends {id, kind, status, delivery_id, data}. Both are read into the same fields.
 */
final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload  the whole decoded body
     */
    public function __construct(
        public string $type,
        public ?string $id,
        public ?int $time,
        public ?string $resourceHref,
        public ?string $environment,
        public array $payload,
        public string $body,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, string $body, ?string $environment = null): self
    {
        $type = $payload['event_type'] ?? $payload['kind'] ?? null;
        $id = $payload['event_id'] ?? $payload['webhook_meta']['webhook_msg_uuid'] ?? $payload['id'] ?? null;
        $time = $payload['event_time'] ?? $payload['webhook_meta']['webhook_msg_timestamp'] ?? null;

        if ($environment === null && isset($payload['live_mode'])) {
            $environment = $payload['live_mode'] ? 'production' : 'sandbox';
        }

        return new self(
            type: is_string($type) && $type !== '' ? $type : 'unknown',
            id: is_scalar($id) ? (string) $id : null,
            time: is_numeric($time) ? (int) $time : (is_string($payload['created'] ?? null) ? (strtotime($payload['created']) ?: null) : null),
            resourceHref: is_string($payload['resource_href'] ?? null) ? $payload['resource_href'] : null,
            environment: $environment,
            payload: $payload,
            body: $body,
        );
    }

    /**
     * The "meta" object: user_id, resource_id, status and so on.
     */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        $meta = is_array($this->payload['meta'] ?? null) ? $this->payload['meta'] : [];

        return $key === null ? $meta : Arr::get($meta, $key, $default);
    }

    /**
     * The id of the trip, order, delivery or store the event is about.
     */
    public function resourceId(): ?string
    {
        $id = $this->meta('resource_id')
            ?? $this->payload['delivery_id']
            ?? $this->payload['store_id']
            ?? $this->meta('order_id');

        return is_scalar($id) ? (string) $id : null;
    }

    /**
     * The new status, when the event carries one.
     */
    public function status(): ?string
    {
        $status = $this->meta('status') ?? $this->payload['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * A value from the payload, by dot path.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->payload, $key, $default);
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    /**
     * The Laravel event name dispatched for this notification, e.g. "uber.requests.status_changed"
     * or "uber.event.delivery_status".
     */
    public function eventName(): string
    {
        return 'uber.'.$this->type;
    }
}
