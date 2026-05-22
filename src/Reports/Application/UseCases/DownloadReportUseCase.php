<?php

declare(strict_types=1);

namespace Src\Reports\Application\UseCases;

use Src\Reports\Domain\Ports\ReportStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadReportUseCase
{
    public function __construct(
        private readonly ReportStorage $storage
    ) {}

    public function execute(string $filename): StreamedResponse
    {
        return $this->storage->download($filename);
    }
}
