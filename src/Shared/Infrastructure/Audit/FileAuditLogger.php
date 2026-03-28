<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Support\Facades\Log;
use Src\Shared\Domain\Audit\AuditLogger;

class FileAuditLogger implements AuditLogger
{
    /** @param array<string, mixed> $payload */
    public function log(string $action, string $entityType, string $entityId, ?string $actorId = null, array $payload = []): void
    {
        Log::info($action, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_id' => $actorId,
            'payload' => $payload,
        ]);
    }
}
