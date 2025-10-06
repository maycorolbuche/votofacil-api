<?php

namespace App\Helpers;

use App\Models\Room;
use App\Models\Vote;
use App\Models\User;
use App\Models\Device;
use App\Models\Candidate;
use App\Models\Config;
use App\Helpers\Cache;
use Carbon\Carbon;


class Cron
{
    public static function init()
    {
        $interval = 60;
        $last_run = Cache::get('cron-last-run');

        try {
            if (!$last_run || Carbon::now()->diffInMinutes($last_run) >= $interval) {
                Cache::set('cron-last-run', Carbon::now());

                //Apaga as salas inativas
                $time = Carbon::now()->subHours(48);
                $rooms = Room::select('id', 'code')
                    ->where('updated_at', '<', $time)
                    ->where('is_active', false)
                    ->whereDoesntHave('devices', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('users', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('candidates', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('configs', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('votes', fn($q) => $q->where('updated_at', '>=', $time))
                    ->get();
                foreach ($rooms as $room) {
                    Vote::where('room_id', $room->id)->delete();
                    User::where('room_id', $room->id)->delete();
                    Device::where('room_id', $room->id)->delete();
                    Candidate::where('room_id', $room->id)->delete();
                    Config::where('room_id', $room->id)->delete();

                    $room->status = 'deleted';
                    $room->save();
                }


                //Desativa as salas sem atividade
                $time = Carbon::now()->subHours(8);
                $rooms = Room::select('id', 'code')
                    ->where('updated_at', '<', $time)
                    ->where('is_active', true)
                    ->whereDoesntHave('devices', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('users', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('candidates', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('configs', fn($q) => $q->where('updated_at', '>=', $time))
                    ->whereDoesntHave('votes', fn($q) => $q->where('updated_at', '>=', $time))
                    ->get();
                foreach ($rooms as $room) {
                    $room->is_active = false;
                    $room->save();
                }
            }
        } catch (\Exception $e) {
            Cache::forget('cron-last-run');
        }

        return true;
    }
}
