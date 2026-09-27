<?php

namespace Tests;

use App\Helpers\Cache;
use App\Helpers\JWT;
use App\Models\Candidate;
use App\Models\Config;
use App\Models\Device;
use App\Models\Room;
use App\Models\User;
use App\Models\Vote;

class ProjectionTest extends TestCase
{
    private Room $room;
    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $this->artisan('migrate', ['--force' => true]);
        Cache::clear();
        $_ENV['JWT_SECRET'] = 'projection-tests-only';
        putenv('JWT_SECRET=projection-tests-only');
        $this->hash = str_repeat('a', 64);
        $this->room = Room::create(['code' => 'ROOM1', 'name' => 'Sala de teste', 'is_active' => true, 'status' => 'closed']);
        Config::create(['room_id' => $this->room->id, 'key' => 'view.hash', 'value' => $this->hash]);
        Config::create(['room_id' => $this->room->id, 'key' => 'votes.num_candidates', 'value' => '3']);
    }

    private function read(?string $hash = null): array
    {
        $this->get('/view/' . ($hash ?? $this->hash));
        return json_decode($this->response->getContent(), true);
    }

    private function candidate(string $name = 'Candidato', int $manual = 4): Candidate
    {
        return Candidate::create(['room_id' => $this->room->id, 'name' => $name, 'sequence' => 1, 'admin_votes' => $manual, 'user_votes' => 777, 'total_votes' => 999]);
    }

    public function test_public_payload_hides_all_candidate_tallies_until_closing(): void
    {
        $this->candidate();
        foreach (['closed', 'open'] as $status) {
            $this->room->update(['status' => $status]);
            $data = $this->read();
            $this->assertEquals(200, $this->response->getStatusCode());
            $this->assertSame($status === 'open' ? 'voting' : 'preparing', $data['phase']);
            $this->assertSame(['id', 'name', 'sequence'], array_keys($data['room']['candidates'][0]));
            $this->assertArrayNotHasKey('view', $data);
            $this->assertArrayNotHasKey('uuid', $data['room']);
            $this->assertStringContainsString('no-store', $this->response->headers->get('Cache-Control'));
        }
        $this->room->update(['status' => 'closed']);
        $data = $this->read();
        $this->assertSame('results', $data['phase']);
        $this->assertSame(4, $data['room']['candidates'][0]['total_votes']);
        // A newly connected screen gets the same result with no client history.
        $this->assertSame('results', $this->read()['phase']);
        $this->room->update(['status' => 'open']);
        $this->assertArrayNotHasKey('total_votes', $this->read()['room']['candidates'][0]);
    }

    public function test_progress_is_individual_without_revealing_choices(): void
    {
        $candidate = $this->candidate();
        $device = Device::create(['room_id' => $this->room->id, 'status' => 'approved']);
        $first = User::create(['room_id' => $this->room->id, 'device_id' => $device->id, 'name' => 'Eleitor 1', 'is_primary' => true]);
        User::create(['room_id' => $this->room->id, 'device_id' => $device->id, 'name' => 'Eleitor 2']);
        $vote = new Vote;
        $vote->room_id = $this->room->id;
        $vote->device_id = $device->id;
        $vote->user_id = $first->id;
        $vote->candidate_id = $candidate->id;
        $vote->save();
        $this->room->update(['status' => 'open']);
        $data = $this->read();
        $users = $data['room']['devices'][0]['users'];
        $this->assertSame([1, 0], array_column($users, 'votes_count'));
        $this->assertSame(['id', 'name', 'votes_count'], array_keys($users[0]));
        $this->assertArrayNotHasKey('device_id', $users[0]);
        $this->assertArrayNotHasKey('candidate_id', $users[0]);
        $this->room->update(['status' => 'closed']);
        $this->assertSame(5, $this->read()['room']['candidates'][0]['total_votes']);
    }

    public function test_existing_link_renewal_and_revocation_still_work(): void
    {
        $token = JWT::sign(['type' => 'admin', 'room_id' => $this->room->id]);
        $headers = ['HTTP_AUTHORIZATION' => 'admin=' . $token];
        $this->call('POST', '/admin/view', [], [], [], $headers);
        $this->assertEquals(200, $this->response->getStatusCode());
        $newHash = json_decode($this->response->getContent(), true)['value'];
        $this->read();
        $this->assertEquals(404, $this->response->getStatusCode());
        $this->read($newHash);
        $this->assertEquals(200, $this->response->getStatusCode());
        $this->call('DELETE', '/admin/view', [], [], [], $headers);
        $this->assertEquals(200, $this->response->getStatusCode());
        $this->read($newHash);
        $this->assertEquals(404, $this->response->getStatusCode());
    }

    public function test_invalid_links_and_inactive_rooms_are_not_exposed(): void
    {
        foreach (['invalid', str_repeat('b', 64)] as $hash) {
            $this->read($hash);
            $this->assertEquals(404, $this->response->getStatusCode());
        }
        $this->room->update(['is_active' => false]);
        $this->read();
        $this->assertEquals(404, $this->response->getStatusCode());
        $this->room->update(['is_active' => true, 'status' => 'deleted']);
        $this->read();
        $this->assertEquals(404, $this->response->getStatusCode());
    }

    public function test_room_isolation_and_clearing_results(): void
    {
        $this->candidate();
        $other = Room::create(['code' => 'OTHER', 'is_active' => true]);
        Candidate::create(['room_id' => $other->id, 'name' => 'Outra sala', 'sequence' => 1]);
        $this->room->update(['status' => 'open']);
        $this->room->update(['status' => 'closed']);
        $data = $this->read();
        $this->assertCount(1, $data['room']['candidates']);
        $token = JWT::sign(['type' => 'admin', 'room_id' => $this->room->id]);
        $this->call('DELETE', '/admin/vote', [], [], [], ['HTTP_AUTHORIZATION' => 'admin=' . $token]);
        $this->assertEquals(200, $this->response->getStatusCode());
        $data = $this->read();
        $this->assertSame('preparing', $data['phase']);
        $this->assertArrayNotHasKey('total_votes', $data['room']['candidates'][0]);
    }
}
