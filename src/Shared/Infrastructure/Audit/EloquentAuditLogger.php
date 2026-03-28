<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Src\Shared\Domain\Audit\AuditLogger;
use Src\Shared\Infrastructure\Persistence\Models\AuditLogModel;

class EloquentAuditLogger implements AuditLogger
{
    /** @param array<string, mixed> $payload */
    public function log(string $action, string $entityType, string $entityId, ?string $actorId = null, array $payload = []): void
    {
        // 1. Persist to Database (Requirement Option B)
        AuditLogModel::create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_id' => $actorId,
            'payload' => $payload,
        ]);
    }
}
