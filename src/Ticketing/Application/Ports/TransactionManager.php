<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Ports;

interface TransactionManager
{
    public function run(\Closure $callback): mixed;
}
