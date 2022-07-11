<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;

class RoomController extends Controller
{
    protected $jwt;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }

    public function findAll()
    {
        return response()->json(Room::all());
    }

    public function store(Request $request)
    {
        $room = new Room;
        $room->teste = $request->teste;
        $room->save();
        return response()->json($room);
    }

}
