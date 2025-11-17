<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Configs;
use App\Helpers\Cache;
use App\Models\Room;
use App\Models\Candidate;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->input('auth');
        $room_id = $auth['room_id'];

        $users_last_sync = Cache::get('last_sync_' . $room_id, []);

        $data = Cache::get('room_' . $room_id, []);
        if (!empty($data)) {
            $data["users_last_sync"] = $users_last_sync;
            $data["cache"] = true;
            return $data;
        }

        $data["users_last_sync"] = $users_last_sync;

        $data["datetime"] = date('Y-m-d H:i:s');

        $room = Room::select('id', 'code', 'is_active', 'status', 'uuid', 'name')
            ->with([
                'candidates' => function ($q) {
                    $q->select('id', 'sequence', 'position', 'name', 'admin_votes', 'user_votes', 'total_votes', 'room_id')
                        ->orderBy('position')
                        ->orderBy('sequence');
                },
                'devices:id,room_id,status',
                'devices.users' => function ($q) {
                    $q->select('id', 'device_id', 'name', 'is_primary')
                        ->withCount('votes');
                }
            ])
            ->where('id', $room_id)
            ->first();
        $data['room'] = $room;
        $data['configs'] = Configs::all();
        $data['view']['hash'] = Configs::getValue('view.hash', '');

        $votes_count = Candidate::query()
            ->select([
                DB::raw('SUM(admin_votes) as admin_votes'),
                DB::raw('SUM(user_votes) as user_votes'),
                DB::raw('SUM(total_votes) as total_votes'),
                DB::raw('COUNT(0) as total_candidates'),
            ])
            ->where('room_id', $room_id)
            ->first();

        $devices_count = Device::query()
            ->select([
                DB::raw('COUNT(0) as total_devices'),
            ])
            ->where('room_id', $room_id)
            ->first();

        $devices_approved_count = Device::query()
            ->select([
                DB::raw('COUNT(0) as total_approved_devices'),
            ])
            ->where('room_id', $room_id)
            ->where('status', 'approved')
            ->first();

        $devices_pending_count = Device::query()
            ->select([
                DB::raw('COUNT(0) as total_pending_devices'),
            ])
            ->where('room_id', $room_id)
            ->where('status', 'pending')
            ->first();

        $devices_disapproved_count = Device::query()
            ->select([
                DB::raw('COUNT(0) as total_disapproved_devices'),
            ])
            ->where('room_id', $room_id)
            ->where('status', 'disapproved')
            ->first();

        $users_count = User::query()
            ->select([
                DB::raw('COUNT(0) as total_users'),
            ])
            ->where('room_id', $room_id)
            ->first();

        $users_approved_count = User::query()
            ->select([
                DB::raw('COUNT(0) as total_approved_users'),
            ])
            ->where('room_id', $room_id)
            ->whereHas('device', function ($query) {
                $query->where('status', 'approved');
            })
            ->first();

        $users_pending_count = User::query()
            ->select([
                DB::raw('COUNT(0) as total_pending_users'),
            ])
            ->where('room_id', $room_id)
            ->whereHas('device', function ($query) {
                $query->where('status', 'pending');
            })
            ->first();

        $users_disapproved_count = User::query()
            ->select([
                DB::raw('COUNT(0) as total_disapproved_users'),
            ])
            ->where('room_id', $room_id)
            ->whereHas('device', function ($query) {
                $query->where('status', 'disapproved');
            })
            ->first();

        $data['resume'] = [
            'admin_votes' => $votes_count->admin_votes ?? 0,
            'user_votes' => $votes_count->user_votes ?? 0,
            'total_votes' => $votes_count->total_votes ?? 0,
            'total_candidates' => $votes_count->total_candidates ?? 0,
            'total_approved_users' => $users_approved_count->total_approved_users ?? 0,
            'total_pending_users' => $users_pending_count->total_pending_users ?? 0,
            'total_disapproved_users' => $users_disapproved_count->total_disapproved_users ?? 0,
            'total_users' => $users_count->total_users ?? 0,
            'total_approved_devices' => $devices_approved_count->total_approved_devices ?? 0,
            'total_pending_devices' => $devices_pending_count->total_pending_devices ?? 0,
            'total_disapproved_devices' => $devices_disapproved_count->total_disapproved_devices ?? 0,
            'total_devices' => $devices_count->total_devices ?? 0,
        ];

        Cache::set('room_' . $room_id, $data);
        return $data;
    }
}
