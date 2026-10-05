<?php

namespace FLAIRUK\Uber\Http\Controllers;

use FLAIRUK\Uber\Events\WebhookReceived;
use FLAIRUK\Uber\Webhooks\WebhookEvent;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives verified Uber webhooks (see VerifyWebhookSignature), dispatches
 * WebhookReceived and "uber.{type}", and answers 200 with an empty body as Uber asks.
 *
 * Uber retries and may deliver twice or out of order: events are de-duplicated by id,
 * and listeners should fetch the resource for its current state rather than trust order.
 * Queue anything slow; Eats orders must be accepted within minutes.
 */
class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events, Cache $cache, Config $config): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return new Response('Invalid Uber webhook payload.', 400);
        }

        $event = WebhookEvent::fromPayload($payload, $request->getContent(), $request->header('X-Environment'));

        $deduplicate = $config->get('uber.webhooks.deduplicate') && $event->id !== null;
        $store = $cache->store($config->get('uber.webhooks.cache_store'));
        $key = 'uber:webhook:'.sha1($event->type.'|'.$event->id);

        if ($deduplicate && ! $store->add($key, true, (int) $config->get('uber.webhooks.deduplicate_hours', 48) * 3600)) {
            return new Response('', 200);
        }

        try {
            $events->dispatch(new WebhookReceived($event));
            $events->dispatch($event->eventName(), [$event]);
        } catch (\Throwable $e) {
            // Let Uber retry: forget the event so the retry isn't treated as a duplicate.
            if ($deduplicate) {
                $store->forget($key);
            }

            throw $e;
        }

        return new Response('', 200);
    }
}
