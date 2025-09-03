<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\Cache;

class Candidate extends Model
{
    protected $fillable = [
        'room_id',
        'position',
        'sequence',
        'name',
        'admin_votes',
        'user_votes',
        'total_votes',
    ];

    protected static function booted()
    {
        $clearCache = function ($candidate) {
            $room_id = $candidate->room_id;
            Cache::forget('room_' . $room_id);
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleting($clearCache);
    }
}
