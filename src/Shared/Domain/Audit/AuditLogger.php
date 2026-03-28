<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Audit;

interface AuditLogger
{
    /** @param array<string, mixed> $payload */
    public function log(string $action, string $entityType, string $entityId, ?string $actorId = null, array $payload = []): void;
}
