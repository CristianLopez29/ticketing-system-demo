<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Audit;

/**
 * Sentinel actor identifiers for audit entries with no authenticated user behind them
 * (a queue worker, a scheduled command). Everything else is a real user id cast to string.
 */
enum AuditActor: string
{
    case System = 'system';
    case Scheduler = 'scheduler';
}
