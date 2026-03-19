<?php

declare(strict_types=1);

namespace Src\Reports\Domain\Ports;

interface ReportStorage
{
    public function exists(string $filename): bool;
    
    public function download(string $filename): mixed;
}
