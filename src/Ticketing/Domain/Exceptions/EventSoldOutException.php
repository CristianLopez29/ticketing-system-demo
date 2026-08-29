<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Exceptions;

use RuntimeException;

/**
 * Extends RuntimeException so the existing 422 mapping in bootstrap/app.php still applies.
 *
 * It exists as its own class so a sold-out event — the expected outcome for every losing
 * buyer in a contended sale — can be excluded from error reporting without also silencing
 * the genuine RuntimeExceptions that signal a bug.
 */
class EventSoldOutException extends RuntimeException {}
