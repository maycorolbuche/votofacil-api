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
        'is_active'
    ];

    protected static function booted()
    {
        $clearCache = function ($room) {
            $room_id = $room->id;
            Cache::forget('room_' . $room_id);
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
