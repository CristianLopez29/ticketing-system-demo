<?php

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Support\Facades\Log;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Shared\Infrastructure\Persistence\Models\AuditLogModel;

class EloquentAuditLogger implements AuditLogger
{
    /** @param array<string, mixed> $payload */
    public function log(string $action, string $entityType, string $entityId, array $payload = []): void
    {
        // 1. Persist to Database (Requirement Option B)
        AuditLogModel::create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
        ]);

        // 2. Log to File (Requirement Option A - keep existing behavior)
        Log::info($action, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload
        ]);
    }
}
