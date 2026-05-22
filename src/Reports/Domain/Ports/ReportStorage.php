<?php

declare(strict_types=1);

namespace Src\Reports\Domain\Ports;

use Src\Reports\Domain\Exceptions\ReportNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ReportStorage
{
    /** @throws ReportNotFoundException */
    public function download(string $filename): StreamedResponse;
}
