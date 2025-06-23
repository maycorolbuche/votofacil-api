<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Helpers\JWT;
use App\Models\Device;
use App\Models\Room;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(Request $request)
    {
        $room_code = strtoupper(trim($request->room_code ?? ''));

        if ($room_code == '') {
            return response()->json(['error' => 'Código da sala não informado!'], 500);
        }

        $room = Room::firstWhere('code', $room_code);
        if (!$room) {
            return response()->json(['error' => 'Sala "' . $room_code . '" não encontrada!'], 500);
        }

        if ($room->is_active == false) {
            return response()->json(['error' => 'Esta sala não está mais ativa!'], 500);
        }

        try {
            $device = new Device;
            $device->room_id = $room->id;
            $device->save();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Não foi possível entrar na sala - ' . $e->getMessage()], 500);
        }

        $token = JWT::sign([
            'type' => 'user',
            'device_id' => $device->id,
            'room_id' => $room->id,
            'code' => $room->code,
        ]);

        if ($token == "") {
            return response()->json(['error' => 'Não foi possível criar token!'], 500);
        }

        $device->token = $token;

        return $device;
    }

    public function show()
    {
        return request()->input('auth');
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
        $authToken = JWT::isUser();

        if (!$authToken) {
            return response()->json(['error' => 'Token inválido!'], 500);
        }

        $device = Device::where('id', $authToken['device_id'])->delete();

        return $device;
    }
}
