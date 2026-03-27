<?php

declare(strict_types=1);

namespace Src\Shared;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Shared\Infrastructure\Audit\CompositeAuditLogger;
use Src\Shared\Infrastructure\Audit\EloquentAuditLogger;
use Src\Shared\Infrastructure\Audit\FileAuditLogger;

class Bindings extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogger::class, function () {
            return new CompositeAuditLogger(
                new EloquentAuditLogger(),
                new FileAuditLogger()
            );
        });
    }
}
