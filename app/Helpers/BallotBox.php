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
        $position = 1;
        $lastVotes = null;
        $samePosition = 1;

        foreach ($candidates as $index => $candidate) {
            if ($lastVotes !== null && $candidate->total_votes === $lastVotes) {
                $candidate->position = $position - 1;
                $samePosition++;
            } else {
                $candidate->position = $position;
                $position += $samePosition;
                $samePosition = 1;
            }
            $lastVotes = $candidate->total_votes;
            $candidate->save();
        }
    }
}
