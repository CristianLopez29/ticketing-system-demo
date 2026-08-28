<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a user tries to act on a season ticket owned by somebody else.
 */
class NotSeasonTicketOwnerException extends RuntimeException {}
