<?php

namespace FLAIRUK\Uber\Http\Middleware;

use Closure;
use FLAIRUK\Uber\Exceptions\InvalidSignatureException;
use FLAIRUK\Uber\Webhooks\WebhookSignature;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests without a valid X-Uber-Signature (or X-Postmates-Signature) with 403.
 */
class VerifyWebhookSignature
{
    public function __construct(protected readonly WebhookSignature $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = null;

        foreach (WebhookSignature::HEADERS as $name) {
            if (filled($value = $request->header($name))) {
                $header = $value;
                break;
            }
        }

        if ($header === null) {
            throw InvalidSignatureException::missing();
        }

        if (! $this->signature->verify($request->getContent(), $header)) {
            throw InvalidSignatureException::invalid();
        }

        return $next($request);
    }
}
