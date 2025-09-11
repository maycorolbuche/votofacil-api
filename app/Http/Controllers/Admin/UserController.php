<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        /*
                $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        
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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Não foi possível cadastrar seu nome - ' . $e->getMessage()], 500);
        }

        return $user;*/
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
        /* $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        $id = $request->input('id') ?? "";

        if (!$request->has('id')) {
            $users = User::where('room_id', $room_id)
                ->get();
            foreach ($users as $user) {
                $user->delete();
            }

            return $users;
        }
        if (!$id) {
            return response()->json(['error' => 'Código do(a) candidato(a) não informado!'], 500);
        }

        $user = User::where('room_id', $room_id)
            ->where('id', $id)
            ->first();
        if ($user) {
            $user->delete();
        }

        return $user;*/
    }
}
