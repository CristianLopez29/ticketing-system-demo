<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Ticketing\Application\Ports\TransactionManager;

class LaravelTransactionManager implements TransactionManager
{
    public function run(\Closure $callback): mixed
    {
        DB::beginTransaction();

        try {
            $result = $callback();
            DB::commit();

            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
