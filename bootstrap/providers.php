<?php

return [
    App\Providers\AppServiceProvider::class,
    Src\Shared\Bindings::class,
    Src\Security\Bindings::class,
    Src\Reports\Bindings::class,
    Src\Ticketing\Bindings::class,
    Src\Ticketing\TicketingRouteServiceProvider::class,
];
