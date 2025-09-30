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

    protected $appends = ['initials'];

    protected static function booted()
    {
        $clearCache = function ($candidate) {
            $room_id = $candidate->room_id;
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
        static::deleting($clearCache);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function getInitialsAttribute()
    {
        $words = preg_split('/\s+/', trim($this->name));
        if (count($words) === 1) {
            return strtoupper(mb_substr($words[0], 0, 1, 'UTF-8'));
        }
        $first = mb_substr($words[0], 0, 1, 'UTF-8');
        $last = mb_substr($words[count($words) - 1], 0, 1, 'UTF-8');
        return strtoupper($first . $last);
    }
}
