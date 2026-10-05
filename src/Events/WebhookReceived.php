<?php

namespace FLAIRUK\Uber\Events;

use FLAIRUK\Uber\Webhooks\WebhookEvent;

/**
 * Dispatched for every verified Uber webhook, before the per-type "uber.{type}" event.
 */
final readonly class WebhookReceived
{
    public function __construct(public WebhookEvent $event) {}
}
