<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(Request $request)
    {
        $auth = request()->input('auth');

        $data = [];

        $users = User::where('device_id', $auth['device_id'])->get();
        if ($users->count() <= 0) {
            $data['status'] = 'no-users';
            return $data;
        }

        if ($auth['status'] != 'approved') {
            $data['status'] = 'pending';
            return $data;
        }

        return $data;
    }
}
