<?php

namespace FLAIRUK\Uber\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A webhook arrived without a valid signature. Renders as 403.
 */
class InvalidSignatureException extends HttpException
{
    public static function missing(): self
    {
        return new self(403, 'Missing Uber webhook signature.');
    }

    public static function invalid(): self
    {
        return new self(403, 'Invalid Uber webhook signature.');
    }
}
