<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function store(Request $request)
    {
        $auth = $request->input('auth');

        $room_id =  $auth['room_id'];
        $name = trim($request->name ?? '');

        if ($name == '') {
            return response()->json(['error' => 'Nome da pessoa não informado!'], 500);
        }

        $sequence = Candidate::where('room_id', $room_id)->max('sequence') + 1;

        $candidate = Candidate::where('room_id', $room_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($candidate) {
            return response()->json(['error' => 'Pessoa "' . $name . '" já foi cadastrada!'], 500);
        }

        try {
            $candidate = new Candidate;
            $candidate->room_id = $room_id;
            $candidate->name = $name;
            $candidate->sequence = $sequence;
            $candidate->save();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return $candidate;
    }

    public function update(Request $request, Candidate $candidate)
    {
        $room_id = $request->input('auth')['room_id'];

        $data = $request->only(['id', 'name']);

        if (!isset($data['id'])) {
            return response()->json(['error' => 'ID não informado.'], 500);
        }
        if (isset($data['name'])) {
            if ($data['name'] == '') {
                return response()->json(['error' => 'Nome da pessoa não informado!'], 500);
            }

            $candidate = Candidate::where('room_id', $room_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->whereNot('id', $data['id'])
                ->first();
            if ($candidate) {
                return response()->json(['error' => 'Já existe uma pessoa com o nome "' . $data['name'] . '"!'], 500);
            }
        }


        $candidate = Candidate::where('room_id', $room_id)->where('id', $data['id'])->first();
        foreach ($data as $key => $value) {
            $candidate->$key = $value;
        }

        if ($candidate->save()) {
            return response()->json($candidate, 200);
        } else {
            return response()->json(['error' => 'Não foi possível atualizar os dados.'], 500);
        }
    }

    public function destroy(Request $request)
    {
        $auth = $request->input('auth');
        $id = $request->input('id') ?? "";

        if (!$auth) {
            return response()->json(['error' => 'Token inválido!'], 500);
        }
        if (!$id) {
            return response()->json(['error' => 'Código da pessoa não informado!'], 500);
        }

        $candidate = Candidate::where('room_id', $auth['room_id'])
            ->where('id', $id)
            ->delete();

        return $candidate;
    }
}
