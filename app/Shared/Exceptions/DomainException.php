<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Base exception for business rule violations inside Domain Services.
 */
class DomainException extends RuntimeException
{
}

