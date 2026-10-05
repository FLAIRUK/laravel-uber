<?php

namespace FLAIRUK\Uber\Exceptions;

/**
 * The request clashes with the current state (409): a rider already on a
 * trip, a surge that must be accepted, a delivery that can no longer be cancelled.
 */
class ConflictException extends UberException
{
    /**
     * Riders API: the surge confirmation link to send the rider to, when
     * the 409 is "surge" and the product needs an accepted surge multiplier.
     */
    public function surgeConfirmationUrl(): ?string
    {
        $url = $this->metadata['surge_confirmation']['href']
            ?? $this->response?->json('meta.surge_confirmation.href');

        return is_string($url) ? $url : null;
    }

    public function surgeConfirmationId(): ?string
    {
        $id = $this->metadata['surge_confirmation']['surge_confirmation_id']
            ?? $this->response?->json('meta.surge_confirmation.surge_confirmation_id');

        return is_string($id) ? $id : null;
    }
}
