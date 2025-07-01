<?php

namespace App\Helpers;

use App\Models\Config;

class Configs
{
    public static function room_id()
    {
        $auth = request()->input('auth');
        $room_id = $auth['room_id'];
        return $room_id;
    }
    public static function configs()
    {
        return [
            'candidates' => [
                'title' => 'Candidatos',
                'items' => [
                    'repeated_names' => [
                        'title' => 'Inserir nome repetido',
                        'type' => 'options',
                        'options' => [
                            'block' => 'Bloquear',
                            'vote' => 'Adicionar 1 voto',
                        ],
                        'default' => 'block'
                    ],
                    'insert_number' => [
                        'title' => 'Inserir número',
                        'type' => 'options',
                        'options' => [
                            'block' => 'Bloquear',
                            'allow' => 'Permitir',
                            'vote' => 'Adicionar 1 voto ao candidato correspondente',
                        ],
                        'default' => 'block'
                    ],
                    'initial_votes' => [
                        'title' => 'Qtd. inicial de votos',
                        'type' => 'integer',
                        'default' => 0
                    ],
                ]
            ],
            'candidates55' => [
                'title' => 'Candidatos 45 Eleitores',
                'items' => [
                    'test' => [
                        'title' => 'Teste 1',
                        'type' => 'boolean',
                        'default' => 'true'
                    ],
                    'test2' => [
                        'title' => 'Teste 45',
                        'type' => 'integer',
                        'default' => '415'
                    ],
                ]
            ]
        ];
    }
    public static function convert($type, $value)
    {
        if ($type == "boolean") {
            $value = (bool)$value;
        } elseif ($type == "integer") {
            $value = (int)$value;
        }
        return $value;
    }
    public static function list()
    {
        $list = [];
        $configs = self::configs();
        foreach ($configs as $groupKey => $config) {
            foreach ($config['items'] as $itemKey => $item) {
                $key = "$groupKey.$itemKey";
                $list[$key] = $item;
            }
        }
        return $list;
    }
    public static function register($room_id = null)
    {
        if (is_null($room_id)) {
            $room_id = self::room_id();
        }

        $configs = self::configs();
        foreach ($configs as $groupKey => $config) {
            foreach ($config['items'] as $itemKey => $item) {
                $key = "$groupKey.$itemKey";
                Config::firstOrCreate(
                    ['room_id' => $room_id, 'key' => $key],
                    ['value' => $item['default']]
                );
            }
        }
    }
    public static function all()
    {
        $room_id = self::room_id();

        $values = Config::where('room_id', $room_id)->get()->keyBy('key');
        if ($values->isEmpty()) {
            self::register();
            $values = Config::where('room_id', $room_id)->get()->keyBy('key');
        }
        $configs = self::configs();
        foreach ($configs as $groupKey => $config) {
            foreach ($config['items'] as $itemKey => $item) {
                $key = "$groupKey.$itemKey";
                $value = $values[$key]->value ?? $item['default'];
                $value = self::convert($item['type'], $value);
                $configs[$groupKey]['items'][$itemKey]['value'] = $value;
            }
        }

        return $configs;
    }
    public static function get($key)
    {
        $room_id = self::room_id();

        $data = Config::where('room_id', $room_id)->where('key', $key)->first();
        if (!$data) {
            self::register();
            $data = Config::where('room_id', $room_id)->where('key', $key)->first();
        }

        $configs = Configs::list();
        if (!isset($configs[$key])) {
            return response()->json(['error' => 'Chave "' . $key . '" não existe!'], 500);
        }

        $value = $data->value;

        $config_item = $configs[$key];
        $value = self::convert($config_item['type'], $value);

        return $value;
    }
}
