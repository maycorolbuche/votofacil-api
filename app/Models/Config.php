<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\Cache;

class Config extends Model
{
    protected $fillable = [
        'room_id',
        'key',
        'value',
    ];

    protected static function booted()
    {
        $clearCache = function ($config) {
            $room_id = $config->room_id;
            Cache::forget('room_' . $room_id);

            $room = Room::where('id', $room_id)->first();
            if ($room["status"] == "open") {
                foreach ($room->devices as $device) {
                    Cache::forget('device_' . $device->id);
                }
            }
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }
}
