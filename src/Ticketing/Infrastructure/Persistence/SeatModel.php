<?php

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\SeatModelFactory;

class SeatModel extends Model
{
    use HasFactory;

    protected $table = 'seats';
    protected $fillable = [
        'event_id', 'row', 'number', 'price_amount', 'price_currency', 'reserved_by_user_id'
    ];

    protected static function newFactory()
    {
        return SeatModelFactory::new();
    }
}
