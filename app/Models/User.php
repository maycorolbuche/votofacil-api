<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'id_user', 'id_room', 'id_device', 'name', 'type',
    ];

}
