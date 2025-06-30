<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Configs;
use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function store(Request $request)
    {
        $auth = $request->input('auth');

        $room_id =  $auth['room_id'];
        $key = trim($request->key ?? '');
        $value = trim($request->value ?? '');

        if ($key == '') {
            return response()->json(['error' => 'Chave não informada!'], 500);
        }

        $configs = Configs::list();
        if (!isset($configs[$key])) {
            return response()->json(['error' => 'Chave "' . $key . '" não existe!'], 500);
        }
        $config_item = $configs[$key];

        if ($config_item['type'] == "boolean") {
            $value = (bool)$value;
        } elseif ($config_item['type'] == "integer") {
            $value = (int)$value;
        }

        try {
            $config = Config::updateOrCreate(
                ['room_id' => $room_id, 'key' => $key],
                ['value' => $value]
            );

            return response()->json($config, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return $config;
    }
}
