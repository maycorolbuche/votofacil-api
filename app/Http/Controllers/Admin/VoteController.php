<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vote;
use App\Models\Candidate;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function destroy(Request $request)
    {
        $auth = $request->input('auth');
        $room_id =  $auth['room_id'];

        $candidates = Candidate::where('room_id', $room_id)->get();
        foreach ($candidates as $candidate) {
            $candidate->admin_votes = 0;
            $candidate->user_votes = 0;
            $candidate->total_votes = 0;
            $candidate->position = 0;
            $candidate->save();
        }

        $votes = Vote::where('room_id', $room_id)->get();
        foreach ($votes as $vote) {
            $vote->delete();
        }

        return $votes;
    }
}
