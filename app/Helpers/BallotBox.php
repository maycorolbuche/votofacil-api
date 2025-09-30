<?php

namespace App\Helpers;

use App\Models\Candidate;
use App\Models\Vote;

class BallotBox
{
    public static function sum($id_candidate)
    {
        $candidate = Candidate::where('id', $id_candidate)->first();
        if ($candidate) {
            $admin_votes = (int)$candidate->admin_votes ?? 0;
            $user_votes = (int)$candidate->user_votes ?? 0;
            $candidate->admin_votes = $admin_votes;
            $candidate->user_votes = $user_votes;
            $candidate->total_votes = $admin_votes + $user_votes;
            $candidate->save();
        }
        self::calculatePositions($candidate->room_id);
    }
    public static function calculatePositions($room_id)
    {
        $candidates = Candidate::where('room_id', $room_id)->orderByDesc('total_votes')->get();
        $position = 0;
        $lastVotes = null;

        foreach ($candidates as $index => $candidate) {
            if ($candidate->total_votes !== $lastVotes) {
                $position++;
            }
            $candidate->position = $position;
            $lastVotes = $candidate->total_votes;
            $candidate->save();
        }
    }
    public static function poll($room_id)
    {
        $candidates = Candidate::where('room_id', $room_id)->withCount('votes')->get();
        foreach ($candidates as $candidate) {
            $candidate->user_votes = $candidate->votes_count;
            $candidate->save();

            self::sum($candidate->id);
        }
        self::calculatePositions($room_id);
    }
}
