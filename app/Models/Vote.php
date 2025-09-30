<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\Cache;

class Vote extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'candidate_id',
    ];

    protected static function booted()
    {
        $clearCache = function ($vote) {
            $room_id = $vote->room_id;
            $device_id = $vote->user->device_id;
            Cache::forget('room_' . $room_id);
            Cache::forget('device_' . $device_id);
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
