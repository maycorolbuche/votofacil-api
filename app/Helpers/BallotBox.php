<?php

namespace App\Helpers;

use App\Models\Candidate;

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
