<?php

namespace App\Http\Controllers;

use App\Helpers\JWT;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct()
    {

    }

    public function index()
    {
        //
    }

    private function hash($len = 5)
    {
        $chr = [
            "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M",
            "N", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z",
            "1", "2", "3", "4", "5", "6", "7", "8", "9",
        ];
        $hash = "";
        for ($i = 1; $i <= $len; $i++) {
            $hash .= $chr[mt_rand(0, count($chr) - 1)];
        }
        return $hash;
    }

    public function create(Request $request)
    {
        $err = true;
        $len = 5;

        while ($err && $len < 10) {
            try {
                $hash = $this->hash($len);
                $room = new Room;
                $room->hash = $hash;
                $room->name = $request->name;
                $room->save();
                $err = false;
            } catch (\Exception $e) {
                $err = true;
                $len++;
            }
        }

        if ($err) {
            return response()->json(['error' => 'Não foi possível criar uma sala!'], 404);
        }

        $token = JWT::sign([
            'type' => 'admin',
            'id' => $room->id,
            'hash' => $room->hash,
        ]);

        if ($token == "") {
            return response()->json(['error' => 'Não foi possível criar token!'], 404);
        }

        $room->token = $token;

        return $room;
    }

    public function store(Request $request)
    {
        $room = new Room;
        $room->teste = $request->teste;
        $room->save();
        return response()->json($room);
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
/*
public function findAll()
{
$token = JWT::sign([
'uid' => 1,
'email' => 'emaggil@email.com',
'asd' => 'emaggil@AAADD',
]);

echo "<pre>";
echo $token."\r\n";
$result = JWT::verify($token);
if (!$result){
echo "<br>eeeeeee";
}else{
print_r($result);
}

print $token;
// return response()->json(Room::all());
}
 */
}
