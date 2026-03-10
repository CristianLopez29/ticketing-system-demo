<?php

namespace Src\Ticketing\Infrastructure\Persistence;

use Database\Factories\SeatModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatModel extends Model
{
    /** @use HasFactory<\Database\Factories\SeatModelFactory> */
    use HasFactory;

    protected $table = 'seats';

    protected $fillable = [
        'event_id', 'row', 'number', 'price_amount', 'price_currency', 'reserved_by_user_id',
    ];

    protected static function newFactory(): SeatModelFactory
    {
        return SeatModelFactory::new();
    }
}
