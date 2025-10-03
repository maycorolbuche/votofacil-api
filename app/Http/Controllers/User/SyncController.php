<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Helpers\Cache;
use App\Models\Room;
use App\Models\User;
use App\Helpers\Configs;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SyncController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->input('auth');
        $device_id = $auth['device_id'];
        $room_id = $auth['room_id'];

        $users_last_sync = Cache::get('last_sync_' . $room_id, []);
        $users_last_sync[$device_id] = Carbon::now();
        Cache::set('last_sync_' . $room_id, $users_last_sync);

        $data = Cache::get('device_' . $device_id, []);
        if (!empty($data)) {
            $data["cache"] = true;
            return $data;
        }

        $data["datetime"] = date('Y-m-d H:i:s');

        $room = Room::where('id', $room_id)->first();
        $data['room_name'] = $room['name'];

        $users = User::select(['id', 'name', 'is_primary'])
            ->where('device_id', $device_id)
            ->where('room_id', $room_id)
            ->get();
        if ($users->count() <= 0) {
            $data['status'] = 'no-users';
        } elseif ($auth['status'] != 'approved') {
            $data['status'] = 'pending';
        } else {
            $data['status'] = $room['status'];
        }

        if ($users->count() > 0) {
            $data['users'] = $users->toArray();
        }

        if ($data['status'] == 'open') {
            $votes_max = Configs::get('votes.num_candidates');

            $users_pending = User::select(['id', 'name', 'is_primary'])
                ->withCount('votes')
                ->where('votes_count', '<', $votes_max)
                ->where('device_id', $device_id)
                ->where('room_id', $room_id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get();

            if ($users_pending->count() <= 0) {
                $data['status'] = 'no-votes-left';
            } else {
                $user = $users_pending[0];

                $data['user'] = $user->toArray();
                $data['users_pending_count'] = count($users_pending);
                $data['votes'] = $user->votes->pluck('candidate_id')->toArray();
                $data['votes_max'] = $votes_max;

                $data['candidates'] = $room->candidates->sortBy(function ($candidate) {
                    return mb_strtolower($candidate->name);
                })->values()->map(function ($candidate) {
                    return [
                        'id' => $candidate->id,
                        'sequence' => $candidate->sequence,
                        'name' => $candidate->name,
                    ];
                })->toArray();
            }
        }

        //dd($data);

        Cache::set('device_' . $device_id, $data);
        return $data;
    }
}
