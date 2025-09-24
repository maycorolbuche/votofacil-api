<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Code;
use App\Helpers\JWT;
use App\Helpers\Configs;
use App\Models\Room;
use Illuminate\Http\Request;
use App\Helpers\Cache;

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
        if (!$room) {
            Cache::forget('room_' . $room_id);
            return response()->json(['error' => 'Sala não encontrada!'], 500);
        }

        $data = $request->only(['name', 'status']);
        foreach ($data as $key => $value) {
            $room->$key = $value;
        }

        if ($room->save()) {
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
