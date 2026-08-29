<?php

declare(strict_types=1);

namespace Tests\Shared\Unit\Infrastructure\Audit;

use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Src\Shared\Infrastructure\Audit\FileAuditLogger;
use Tests\TestCase;

class FileAuditLoggerTest extends TestCase
{
    public function test_it_writes_to_the_dedicated_audit_channel(): void
    {
        $channel = Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('info')
            ->once()
            ->with('auth.login_succeeded', Mockery::on(function (array $context) {
                return $context['entity_type'] === 'user'
                    && $context['entity_id'] === '7'
                    && $context['actor_id'] === '7'
                    && $context['payload'] === ['email' => 'a@b.com'];
            }));

        Log::shouldReceive('channel')
            ->once()
            ->with('audit')
            ->andReturn($channel);

        $logger = new FileAuditLogger;
        $logger->log('auth.login_succeeded', 'user', '7', '7', ['email' => 'a@b.com']);
    }
}
