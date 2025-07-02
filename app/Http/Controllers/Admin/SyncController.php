<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Configs;
use App\Helpers\Cache;
use App\Models\Room;
use App\Models\Candidate;
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

        $room = Room::select('id', 'code', 'uuid', 'name')
            ->with(['candidates:id,sequence,position,name,admin_votes,user_votes,total_votes,room_id'])
            ->where('id', $room_id)
            ->first();
        $data['room'] = $room;
        $data['configs'] = Configs::all();

        $votes_count = Candidate::where('room_id', $room_id)
            ->selectRaw('SUM(admin_votes) as admin_votes, SUM(user_votes) as user_votes, SUM(total_votes) as total_votes')
            ->first();
        $data['resume'] = [
            'admin_votes' => $votes_count->admin_votes ?? 0,
            'user_votes' => $votes_count->user_votes ?? 0,
            'total_votes' => $votes_count->total_votes ?? 0,
        ];

        Cache::set('room_' . $room_id, $data);
        return $data;
    }
}
