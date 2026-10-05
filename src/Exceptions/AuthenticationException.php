<?php

namespace FLAIRUK\Uber\Exceptions;

/**
 * A 401 / 403 from Uber, or a token request that was refused.
 */
class AuthenticationException extends UberException {}
