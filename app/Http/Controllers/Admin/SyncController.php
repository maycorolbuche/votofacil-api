<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(Request $request)
    {
        $auth = request()->input('auth');

        $data = [];

        $data = $auth;

        $room = Room::firstWhere('id', $auth['room_id']);
        $data['room'] = $room;

        return $data;
    }
}
