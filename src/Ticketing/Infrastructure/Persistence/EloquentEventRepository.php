<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Model\Event;
use Src\Ticketing\Domain\Repositories\EventRepository;

class EloquentEventRepository implements EventRepository
{

    public function find(int $id): ?Event
    {
        $record = DB::table('events')->find($id);
        if (! $record) {
            return null;
        }

        $data = (array) $record;

        return new Event(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            (int) ($data['total_seats'] ?? 0)
        );
    }

    public function findBySeasonId(int $seasonId): array
    {
        $records = DB::table('events')->where('season_id', $seasonId)->get();

        $events = [];
        foreach ($records as $record) {
            $data     = (array) $record;
            $events[] = new Event(
                (int) ($data['id'] ?? 0),
                (string) ($data['name'] ?? ''),
                (int) ($data['total_seats'] ?? 0)
            );
        }

        return $events;
    }

    public function save(Event $event): void
    {
        $isNew = ! DB::table('events')->where('id', $event->id())->exists();

        if ($isNew) {
            DB::table('events')->insert([
                'id'          => $event->id(),
                'name'        => $event->name(),
                'total_seats' => $event->totalSeats(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('events')->where('id', $event->id())->update([
                'name'        => $event->name(),
                'total_seats' => $event->totalSeats(),
                'updated_at'  => now(),
            ]);
        }
    }
}
