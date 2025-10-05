<?php

namespace App\Helpers;

use App\Models\Room;
use App\Helpers\Cache;
use Carbon\Carbon;

class Cron
{
    public static function init()
    {
        $interval = 1;
        $last_run = Cache::get('cron-last-run');

        if (!$last_run || Carbon::now()->diffInMinutes($last_run) >= $interval) {
            Cache::set('cron-last-run', Carbon::now());

            /*
            AQUI A ROTINA DO CRON DE LIMPEZA
            ..
            ..
            */
        }

        return true;
    }
}
