<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Src\Shared\Domain\Audit\AuditLogger;

class CompositeAuditLogger implements AuditLogger
{
    /** @var AuditLogger[] */
    private array $loggers;

    public function __construct(AuditLogger ...$loggers)
    {
        $this->loggers = $loggers;
    }

    /** @param array<string, mixed> $payload */
    public function log(string $action, string $entityType, string $entityId, ?string $actorId = null, array $payload = []): void
    {
        foreach ($this->loggers as $logger) {
            try {
                $logger->log($action, $entityType, $entityId, $actorId, $payload);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
