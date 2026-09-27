<?php

namespace App\Helpers;

use App\Models\Config;
use App\Models\Room;

class ProjectionState
{
    // Existing closed status means both "not started" and "finished".
    // Store only the presentation phase, without changing voting status/rules.
    public static function record(Room $room): void
    {
        if ($room->wasChanged('status') && in_array($room->status, ['open', 'closed'], true)) {
            self::set($room->id, $room->status === 'open' ? 'voting' : 'results');
        }
    }

    public static function reset($roomId): void
    {
        $room = Room::find($roomId);
        if ($room) {
            self::set($roomId, $room->status === 'open' ? 'voting' : 'preparing');
        }
    }

    private static function set($roomId, string $phase): void
    {
        Config::updateOrCreate(['room_id' => $roomId, 'key' => 'view.phase'], ['value' => $phase]);
    }

    public static function get(Room $room): string
    {
        if ($room->status === 'open') {
            return 'voting';
        }
        $phase = Config::where('room_id', $room->id)->where('key', 'view.phase')->value('value');
        if ($phase === 'results') {
            return 'results';
        }
        // Previously closed rooms with online votes remain readable.
        if ($phase === null && $room->votes()->exists()) {
            return 'results';
        }
        return 'preparing';
    }
}
