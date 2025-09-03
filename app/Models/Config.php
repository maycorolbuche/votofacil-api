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
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }
}
