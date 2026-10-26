<?php

use App\Providers\AppServiceProvider;
use Src\Reports\Bindings as ReportsBindings;
use Src\Security\Bindings as SecurityBindings;
use Src\Shared\Bindings as SharedBindings;
use Src\Shared\SharedRouteServiceProvider;
use Src\Ticketing\Bindings as TicketingBindings;
use Src\Ticketing\TicketingRouteServiceProvider;

return [
    AppServiceProvider::class,
    SharedBindings::class,
    SharedRouteServiceProvider::class,
    SecurityBindings::class,
    ReportsBindings::class,
    TicketingBindings::class,
    TicketingRouteServiceProvider::class,
];
