<?php

namespace App\Helpers;

use App\Models\Candidate;

class BallotBox
{
    public static function sum($id_candidate)
    {
        $candidate = Candidate::where('id', $id_candidate)->first();
        if ($candidate) {
            $candidate->total_votes = $candidate->admin_votes + $candidate->user_votes;
            $candidate->save();
        }
        self::calculatePositions();
    }
    public static function calculatePositions()
    {
        $candidates = Candidate::orderByDesc('total_votes')->get();
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
}
