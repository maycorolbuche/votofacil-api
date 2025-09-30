<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\Cache;

class User extends Model
{
    protected $fillable = [
        'room_id',
        'device_id',
        'is_primary',
        'name',
    ];

    protected static function booted()
    {
        $clearCache = function ($user) {
            $room_id = $user->room_id;
            $device_id = $user->device_id;
            Cache::forget('room_' . $room_id);
            Cache::forget('device_' . $device_id);
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
