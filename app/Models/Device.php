<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\Cache;

class Device extends Model
{
    protected $fillable = [
        'room_id',
        'status',
    ];

    protected static function booted()
    {
        $clearCache = function ($device) {
            $room_id = $device->room_id;
            $device_id = $device->id;
            Cache::forget('room_' . $room_id);
            Cache::forget('device_' . $device_id);
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
