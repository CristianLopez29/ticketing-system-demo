<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Services;

use Ramsey\Uuid\Uuid;
use Src\Shared\Domain\Services\UuidGenerator;

class PhpUuidGenerator implements UuidGenerator
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
