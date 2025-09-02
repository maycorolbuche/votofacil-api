<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\Cache;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $name = trim($request->name ?? '');
        $room_id = $request->input('auth')['room_id'];
        $device_id = $request->input('auth')['device_id'];

        if ($name == '') {
            return response()->json(['error' => 'Nome não informado!'], 500);
        }

        try {
            $user = new User;
            $user->name = $name;
            $user->room_id = $room_id;
            $user->device_id = $device_id;
            $user->is_primary = true;
            $user->save();
            Cache::forget('room_' . $room_id);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Não foi possível cadastrar seu nome - ' . $e->getMessage()], 500);
        }

        return $user;
    }
}
