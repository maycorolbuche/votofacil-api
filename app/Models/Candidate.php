<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = [
        'room_id',
        'position',
        'sequence',
        'name',
        'admin_votes',
        'user_votes',
        'total_votes',
    ];
}
