<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Vote;
use App\Models\User;
use App\Models\Candidate;
use App\Helpers\Configs;
use App\Helpers\BallotBox;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function store(Request $request)
    {
        $room_id = $request->input('auth')['room_id'];
        $device_id = $request->input('auth')['device_id'];
        $user_id = trim($request->user_id ?? '');
        $candidate_id = trim($request->candidate_id ?? '');

        if ($user_id == '') {
            return response()->json(['error' => 'Eleitor não identificado!'], 500);
        }
        if ($candidate_id == '') {
            return response()->json(['error' => 'Candidato não informado!'], 500);
        }

        $room = Room::where('id', $room_id)->where('status', 'open')->first();
        if (!$room) {
            return response()->json(['error' => 'A votação está fechada!'], 500);
        }

        $user = User::where('id', $user_id)->where('device_id', $device_id)->where('room_id', $room_id)->first();
        if (!$user) {
            return response()->json(['error' => 'Eleitor não encontrado!'], 500);
        }

        $candidate = Candidate::where('id', $candidate_id)->where('room_id', $room_id)->first();
        if (!$candidate) {
            return response()->json(['error' => 'Candidato não encontrado!'], 500);
        }

        $vote = Vote::where('user_id', $user_id)->where('candidate_id', $candidate_id)->where('room_id', $room_id)->first();
        if ($vote) {
            return response()->json(['error' => 'Já foi registrado um voto para "' . $candidate->name . '"!'], 500);
        }

        $vote_count = Vote::where('user_id', $user_id)->where('room_id', $room_id)->count();
        $votes_max = Configs::get('votes.num_candidates');
        if ($vote_count >= $votes_max) {
            return response()->json(['error' => 'Seus votos já foram registrados. Não é possível votar novamente!'], 500);
        }

        try {
            $vote = new Vote;
            $vote->user_id = $user_id;
            $vote->candidate_id = $candidate_id;
            $vote->device_id = $device_id;
            $vote->room_id = $room_id;
            $vote->save();
            BallotBox::check($room_id);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Não foi possível registrar seu voto - ' . $e->getMessage()], 500);
        }

        return $vote;
    }
}
