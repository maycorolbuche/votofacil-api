<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\Cache;

class Room extends Model
{
    protected $fillable = [
        'code',
        'uuid',
        'name',
        'status',
        'is_active'
    ];

    protected static function booted()
    {
        $clearCache = function ($room) {
            $room_id = $room->id;
            Cache::forget('room_' . $room_id);
            foreach ($room->devices as $device) {
                Cache::forget('device_' . $device->id);
            }
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::updated([\App\Helpers\ProjectionState::class, 'record']);
        static::deleted($clearCache);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function configs()
    {
        return $this->hasMany(Config::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
