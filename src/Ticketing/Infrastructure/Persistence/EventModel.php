<?php

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EventModel extends Model
{
    protected $table = 'events';
    protected $fillable = ['name', 'total_seats'];
}
