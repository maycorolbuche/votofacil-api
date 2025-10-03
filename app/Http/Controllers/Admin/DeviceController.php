<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Device;
use App\Helpers\BallotBox;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function update(Request $request, Device $device)
    {
        $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        $data = $request->only(['id', 'user_id', 'status']);

        if (!isset($data['id']) && !isset($data['user_id'])) {
            return response()->json(['error' => 'ID não informado.'], 500);
        }

        if (isset($data['user_id'])) {
            $user = User::where('room_id', $room_id)->where('id', $data['user_id'])->first();
            if (!$user) {
                return response()->json(['error' => 'Usuário não encontrado.'], 500);
            }

            $data['id'] = $user->device_id;
            unset($data['user_id']);
        }

        $device = Device::where('room_id', $room_id)->where('id', $data['id'])->first();
        foreach ($data as $key => $value) {
            $device->$key = $value;
        }

        if ($device->save()) {
            BallotBox::check($room_id);
            return response()->json($device, 200);
        } else {
            return response()->json(['error' => 'Não foi possível atualizar os dados.'], 500);
        }
    }
}
