<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Configs;
use App\Models\Room;
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


        return $data;
    }
}
