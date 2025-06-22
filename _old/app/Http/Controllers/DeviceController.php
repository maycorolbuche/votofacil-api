<?php

namespace App\Http\Controllers;

use App\Helpers\JWT;
use App\Models\Device;
use App\Models\User;
use App\Models\Room;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct()
    {

    }

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        if (!$request->name && $request->type_user == 'user') {
            return response()->json(['error' => 'Nome não informado!'], 500);
        }
        if (!$request->hash) {
            return response()->json(['error' => 'Código da sala não informado!'], 500);
        }
        if (!$request->type_user) {
            return response()->json(['error' => 'Tipo de usuário não identificado!'], 500);
        }
        if ($request->type_user != 'user' && $request->type_user != 'preview') {
            return response()->json(['error' => 'Tipo de usuário inválido!'], 500);
        }
        $room = Room::firstWhere('hash', $request->hash);
        if (!$room) {
            return response()->json(['error' => 'Sala não encontrada!'], 500);
        }
        if ($room->status != 'open') {
            return response()->json(['error' => 'Esta sala está trancada! Solicite ao administrador para abrir a sala.'], 500);
        }

        try {
            $device = new Device;
            $device->id_room = $room->id_room;
            $device->type_user = $request->type_user;
            $device->status = 'pending';
            $device->save();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        if ($request->type_user == 'user') {
            try {
                $user = new User;
                $user->id_device = $device->id;
                $user->id_room = $room->id_room;
                $user->name = $request->name;
                $user->save();
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        $token = JWT::sign([
            'type' => $request->type_user,
            'id_device' => $device->id,
            'id_room' => $room->id_room,
            'hash' => $room->hash,
        ]);

        if ($token == "") {
            return response()->json(['error' => 'Não foi possível criar token!'], 500);
        }

        $device->token = $token;

        return $device;
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
