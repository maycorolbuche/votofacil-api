<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Configs;
use App\Helpers\Cache;
use App\Models\Room;
use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->input('auth');
        $room_id = $auth['room_id'];

        $data = Cache::get('room_' . $room_id, []);
        if (!empty($data)) {
            $data["cache"] = true;
            return $data;
        }

        $room = Room::select('id', 'code', 'is_active', 'uuid', 'name')
            ->with(['candidates:id,sequence,position,name,admin_votes,user_votes,total_votes,room_id'])
            ->with([
                'devices:id,room_id,status',
                'devices.users:id,device_id,name,is_primary'
            ])
            ->where('id', $room_id)
            ->first();
        $data['room'] = $room;
        $data['configs'] = Configs::all();

        $votes_count = Candidate::query()
            ->select([
                DB::raw('SUM(admin_votes) as admin_votes'),
                DB::raw('SUM(user_votes) as user_votes'),
                DB::raw('SUM(total_votes) as total_votes'),
                DB::raw('COUNT(0) as total_candidates'),
            ])
            ->where('room_id', $room_id)
            ->first();

        $data['resume'] = [
            'admin_votes' => $votes_count->admin_votes ?? 0,
            'user_votes' => $votes_count->user_votes ?? 0,
            'total_votes' => $votes_count->total_votes ?? 0,
            'total_candidates' => $votes_count->total_candidates ?? 0,
        ];

        Cache::set('room_' . $room_id, $data);
        return $data;
    }
}
