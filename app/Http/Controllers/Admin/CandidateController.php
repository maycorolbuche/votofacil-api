<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Helpers\BallotBox;
use App\Helpers\Configs;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function store(Request $request)
    {
        $auth = $request->input('auth');

        $room_id =  $auth['room_id'];
        $name = $request->name ?? '';
        if (is_array($name)) {
            $inserted = [];
            foreach ($name as $n) {
                $n = trim($n);
                if (mb_strlen($n) > 50) {
                    $n = mb_substr($n, 0, 50);
                }
                $n = trim($n);
                if ($n == '') {
                    continue;
                }
                $exists = Candidate::where('room_id', $room_id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($n)])
                    ->exists();
                if ($exists) {
                    continue;
                }
                $sequence = Candidate::where('room_id', $room_id)->max('sequence') + 1;
                $initial_votes = Configs::get('candidates.initial_votes');
                try {
                    $candidate = new Candidate;
                    $candidate->room_id = $room_id;
                    $candidate->name = $n;
                    $candidate->admin_votes = $initial_votes;
                    $candidate->sequence = $sequence;
                    $candidate->save();
                    BallotBox::sum($candidate->id);
                    $inserted[] = $candidate;
                } catch (\Exception $e) {
                    // ignore errors for individual inserts
                    continue;
                }
            }
            BallotBox::calculatePositions();
            return response()->json($inserted);
        }

        $name = trim($name);

        if ($name == '') {
            return response()->json(['error' => 'Nome do(a) candidato(a) não informado!'], 500);
        }

        $insert_number = Configs::get('candidates.insert_number');
        $is_numeric_name = is_numeric($name);
        if ($is_numeric_name && $insert_number == 'block') {
            return response()->json(['error' => 'Não é possível inserir número!'], 500);
        }
        if ($is_numeric_name && $insert_number == 'vote') {
            $candidate = Candidate::where('room_id', $room_id)
                ->where('sequence', $name)
                ->first();
            if ($candidate) {
                $candidate->increment('admin_votes');
                BallotBox::sum($candidate->id);
                return $candidate;
            } else {
                return response()->json(['error' => 'Não é encontrado candidato(a) de número ' . $name . '!'], 500);
            }
        }

        $candidate = Candidate::where('room_id', $room_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($candidate) {
            $repeated_names = Configs::get('candidates.repeated_names');
            if ($repeated_names == "vote") {
                $candidate->increment('admin_votes');
                BallotBox::sum($candidate->id);
                return $candidate;
            } else {
                return response()->json(['error' => 'Pessoa "' . $name . '" já foi cadastrada!'], 500);
            }
        }

        $sequence = Candidate::where('room_id', $room_id)->max('sequence') + 1;
        $initial_votes = Configs::get('candidates.initial_votes');

        try {
            $candidate = new Candidate;
            $candidate->room_id = $room_id;
            $candidate->name = $name;
            $candidate->admin_votes = $initial_votes;
            $candidate->sequence = $sequence;
            $candidate->save();
            BallotBox::sum($candidate->id);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        BallotBox::calculatePositions();

        return $candidate;
    }

    public function update(Request $request, Candidate $candidate)
    {
        $room_id = $request->input('auth')['room_id'];

        $data = $request->only(['id', 'name', 'admin_votes']);

        if (!isset($data['id'])) {
            return response()->json(['error' => 'ID não informado.'], 500);
        }
        if (isset($data['name'])) {
            if ($data['name'] == '') {
                return response()->json(['error' => 'Nome do(a) candidato(a) não informado!'], 500);
            }

            $candidate = Candidate::where('room_id', $room_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->whereNot('id', $data['id'])
                ->first();
            if ($candidate) {
                return response()->json(['error' => 'Já existe um(a) candidato(a) com o nome "' . $data['name'] . '"!'], 500);
            }
        }


        $candidate = Candidate::where('room_id', $room_id)->where('id', $data['id'])->first();
        foreach ($data as $key => $value) {
            $candidate->$key = $value;
        }

        if ($candidate->save()) {
            BallotBox::sum($candidate->id);
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
        if (!$request->has('id')) {
            $candidate = Candidate::where('room_id', $auth['room_id'])
                ->delete();

            return $candidate;
        }
        if (!$id) {
            return response()->json(['error' => 'Código do(a) candidato(a) não informado!'], 500);
        }

        $candidate = Candidate::where('room_id', $auth['room_id'])
            ->where('id', $id)
            ->delete();

        return $candidate;
    }
}
