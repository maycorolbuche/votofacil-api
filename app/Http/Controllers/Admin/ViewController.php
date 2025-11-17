<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Helpers\BallotBox;
use Illuminate\Http\Request;

class ViewController extends Controller
{
    public function store()
    {
        $auth = request()->input('auth');
        $room_id = $auth['room_id'];

        $maxAttempts = 10;
        $attempt = 0;
        do {
            try {
                $random = random_bytes(32);
            } catch (\Exception $e) {
                $random = openssl_random_pseudo_bytes(32);
            }

            $hash = hash('sha256', $random . uniqid((string) random_int(0, PHP_INT_MAX), true));
            $attempt++;

            if ($attempt >= $maxAttempts && Config::where('key', 'view.hash')->where('value', $hash)->exists()) {
                throw new \RuntimeException('Não foi possível gerar o link após ' . $maxAttempts . ' tentativas.');
            }
        } while (Config::where('key', 'view.hash')->where('value', $hash)->exists());

        try {
            $config = Config::updateOrCreate(
                ['room_id' => $room_id, 'key' => 'view.hash'],
                ['value' => $hash]
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return $config;
    }

    public function destroy()
    {
        $auth = request()->input('auth');
        $room_id =  $auth['room_id'];

        $config = Config::where('room_id', $room_id)->where('key', 'view.hash')->first();
        if ($config) {
            $config->delete();
        }

        return $config;
    }
}
