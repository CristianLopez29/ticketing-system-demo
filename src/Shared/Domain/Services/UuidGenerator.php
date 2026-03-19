<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Services;

interface UuidGenerator
{
    public function generate(): string;
}
