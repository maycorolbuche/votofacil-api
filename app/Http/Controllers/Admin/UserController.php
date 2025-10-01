<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        $name = trim($request->name ?? '');
        $device_id = $request->device_id ?? '';

        if ($name == '') {
            return response()->json(['error' => 'Nome não informado!'], 500);
        }

        if ($device_id == '') {
            return response()->json(['error' => 'Dispositivo não informado!'], 500);
        }

        $device = Device::where('room_id', $room_id)->where('id', $device_id)->first();
        if (!$device) {
            return response()->json(['error' => 'Dispositivo não encontrado!'], 500);
        }

        try {
            $user = new User;
            $user->name = $name;
            $user->room_id = $room_id;
            $user->device_id = $device_id;
            $user->is_primary = false;
            $user->save();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return $user;
    }

    public function update(Request $request, User $user)
    {
        $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        $data = $request->only(['id', 'name']);

        if (!isset($data['id'])) {
            return response()->json(['error' => 'ID não informado.'], 500);
        }
        if (isset($data['name'])) {
            if ($data['name'] == '') {
                return response()->json(['error' => 'Nome do(a) usuário(a) não informado!'], 500);
            }
        }


        $user = User::where('room_id', $room_id)->where('id', $data['id'])->first();
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }

        if ($user->save()) {
            return response()->json($user, 200);
        } else {
            return response()->json(['error' => 'Não foi possível atualizar os dados.'], 500);
        }
    }

    public function destroy(Request $request)
    {
        $auth = $request->input('auth');
        $room_id =  $auth['room_id'];
        $remove_device_id = null;

        $id = $request->input('id') ?? "";

        if (!$id) {
            return response()->json(['error' => 'Código do(a) usuário(a) não informado!'], 500);
        }

        $user = User::where('room_id', $room_id)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Usuário(a) não encontrado!'], 500);
        }

        if ($user->is_primary) {
            User::where('room_id', $room_id)
                ->where('device_id', $user->device_id)
                ->whereNot('id', $id)
                ->delete();
            $remove_device_id = $user->device_id;
        }

        if ($user) {
            $user->delete();
        }

        if ($remove_device_id) {
            $device = Device::where('room_id', $room_id)
                ->where('id', $remove_device_id)
                ->first();
            if ($device) {
                $device->delete();
            }
        }

        $vote = Vote::where('room_id', $room_id)
            ->where('user_id', $user->id)
            ->first();
        if ($vote) {
            $vote->delete();
        }

        return $user;
    }
}
