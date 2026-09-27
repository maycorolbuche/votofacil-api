<?php

namespace App\Http\Controllers;

use App\Helpers\ProjectionState;
use App\Models\Config;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class ProjectionController extends Controller
{
    public function show(string $hash)
    {
        // Reuse exactly the existing share token. No JWT or admin session.
        $payload = preg_match('/^[a-f0-9]{64}$/D', $hash) ? DB::transaction(function () use ($hash) {
            $link = Config::where('key', 'view.hash')->where('value', $hash)->first();
            if (!$link || !hash_equals((string) $link->value, $hash)) {
                return null;
            }
            $room = Room::where('id', $link->room_id)->where('is_active', true)
                ->whereIn('status', ['open', 'closed'])->first();
            if (!$room) {
                return null;
            }
            $phase = ProjectionState::get($room);
            $results = $room->status === 'closed' && $phase === 'results';
            $query = $room->candidates()->select('id', 'name', 'sequence')->orderBy('sequence')->orderBy('id');
            if ($results) {
                // Count persisted ballots to avoid stale denormalized totals
                // during automatic closing. Never select tallies while open.
                $query->addSelect('admin_votes')->withCount('votes');
            }
            $candidates = $query->get()->map(function ($candidate) use ($results) {
                $item = ['id' => $candidate->id, 'name' => $candidate->name, 'sequence' => $candidate->sequence];
                if ($results) {
                    $item['total_votes'] = (int) $candidate->admin_votes + (int) $candidate->votes_count;
                }
                return $item;
            })->values()->all();
            $devices = $room->devices()->whereIn('status', ['approved', 'pending'])
                ->with(['users' => function ($query) {
                    $query->select('id', 'device_id', 'name')->withCount('votes')->orderBy('id');
                }])->orderBy('id')->get()->map(function ($device) {
                    return [
                        'status' => $device->status,
                        'users' => $device->users->map(function ($user) {
                            // Participation counts only; no candidate choices or JWTs.
                            return ['id' => $user->id, 'name' => $user->name, 'votes_count' => $user->votes_count];
                        })->values()->all(),
                    ];
                })->values()->all();
            $limit = Config::where('room_id', $room->id)->where('key', 'votes.num_candidates')->value('value');
            return [
                'phase' => $phase,
                'room' => [
                    'id' => $room->id, 'name' => $room->name, 'code' => $room->code,
                    'status' => $room->status, 'is_active' => true,
                    'candidates' => $candidates, 'devices' => $devices,
                ],
                'configs' => ['votes' => ['items' => ['num_candidates' => ['value' => max(1, (int) ($limit ?? 1))]]]],
            ];
        }) : null;

        // No shared cache: revocation and reopening take effect on next poll.
        return response()->json($payload ?? ['error' => 'Link de projeção indisponível.'], $payload === null ? 404 : 200)
            ->header('Cache-Control', 'no-store, private, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
