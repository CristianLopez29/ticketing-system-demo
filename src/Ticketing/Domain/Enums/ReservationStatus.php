<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Enums;

enum ReservationStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
