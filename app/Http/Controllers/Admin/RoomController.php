<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Code;
use App\Helpers\JWT;
use App\Helpers\Configs;
use App\Helpers\Cache;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function store()
    {
        $err = true;
        $err_description = '';
        $len = 5;

        while ($err && $len < 10) {
            try {
                $code = Code::generate($len);
                $room = new Room;
                $room->code = $code;
                $room->save();
                $err = false;
            } catch (\Exception $e) {
                $err = true;
                $err_description = $e->getMessage();
                $len++;
            }
        }

        if ($err) {
            return response()->json(['error' => 'Não foi possível criar uma sala - ' . $err_description], 500);
        }

        Configs::register($room->id);

        $token = JWT::sign([
            'type' => 'admin',
            'room_id' => $room->id,
            'code' => $room->code,
        ]);

        Cache::forget('room_' . $room->id);

        if ($token == "") {
            return response()->json(['error' => 'Não foi possível criar token!'], 500);
        }

        $room->token = $token;

        return $room;
    }

    public function show(Request $request)
    {
        return $request->input('auth');
    }

    public function update(Request $request, Room $room)
    {
        $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        $room = Room::find($room_id);

        $data = $request->only(['name']);
        foreach ($data as $key => $value) {
            $room->$key = $value;
        }

        if ($room->save()) {
            Cache::forget('room_' . $room_id);
            return response()->json($room, 200);
        } else {
            return response()->json(['error' => 'Não foi possível atualizar a sala.'], 500);
        }
    }

    public function destroy(Room $room)
    {
        //
    }
}
