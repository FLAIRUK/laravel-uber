<?php

namespace FLAIRUK\Uber\Exceptions;

/**
 * Too many requests (429).
 */
class RateLimitException extends UberException
{
    /**
     * Seconds to wait before retrying, from Retry-After or X-Rate-Limit-Reset (a Unix
     * timestamp), or 60 if Uber sent neither.
     */
    public function retryAfter(): int
    {
        $retryAfter = $this->response?->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return max(1, (int) $retryAfter);
        }

        $reset = $this->response?->header('X-Rate-Limit-Reset');

        if (is_numeric($reset)) {
            return max(1, (int) $reset - time());
        }

        return 60;
    }
}
