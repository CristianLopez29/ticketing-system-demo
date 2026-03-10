<?php

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Src\Ticketing\Infrastructure\Persistence\Observers\EventObserver;

class EventModel extends Model
{
    protected $table = 'events';

    protected $fillable = ['name', 'total_seats'];

    protected static function booted(): void
    {
        static::observe(EventObserver::class);
    }
}
