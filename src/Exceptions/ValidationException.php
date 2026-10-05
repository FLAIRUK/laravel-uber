<?php

namespace FLAIRUK\Uber\Exceptions;

/**
 * Uber rejected the request body (400 or 422), e.g. an address it cannot
 * geocode or a surge confirmation that is required. Check $errorCode.
 */
class ValidationException extends UberException {}
