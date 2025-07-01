<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Configs;
use App\Models\Room;
use App\Models\Candidate;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->input('auth');

        $room_id = $auth['room_id'];

        $data = [];

        $room = Room::select('id', 'code', 'uuid', 'name')
            ->with(['candidates:id,sequence,position,name,admin_votes,user_votes,total_votes,room_id'])
            ->where('id', $room_id)
            ->first();
        $data['room'] = $room;
        $data['configs'] = Configs::all();

        $votes_count = Candidate::where('room_id', $room_id)
            ->selectRaw('SUM(admin_votes) as admin_votes, SUM(user_votes) as user_votes, SUM(total_votes) as total_votes')
            ->first();
        $data['resume'] = $votes_count;

        return $data;
    }
}
