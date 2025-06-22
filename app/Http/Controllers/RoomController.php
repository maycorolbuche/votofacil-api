<?php

namespace App\Http\Controllers;

use App\Helpers\Code;
use App\Helpers\JWT;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct() {}

    public function create(Request $request)
    {
        //
    }

    public function store(Request $request)
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
            return response()->json(['error' => 'Não foi possível criar uma sala!', 'description' => $err_description], 500);
        }

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

    public function show(Room $room)
    {
        //
    }

    public function edit(Room $room)
    {
        //
    }

    public function update(Request $request, Room $room)
    {
        //
    }

    public function destroy(Room $room)
    {
        //
    }
}
