<?php

namespace App\Helpers;

use App\Models\Config;

class Configs
{
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
    public static function register($room_id)
    {
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
    public static function all($room_id)
    {
        $values = Config::where('room_id', $room_id)->get()->keyBy('key');
        if ($values->isEmpty()) {
            self::register($room_id);
            $values = Config::where('room_id', $room_id)->get()->keyBy('key');
        }
        $configs = self::configs();
        foreach ($configs as $groupKey => $config) {
            foreach ($config['items'] as $itemKey => $item) {
                $key = "$groupKey.$itemKey";
                $value = $values[$key]->value ?? $item['default'];
                if ($item['type'] == 'boolean') {
                    $value = (bool)$value;
                } elseif ($item['type'] == 'integer') {
                    $value = (int)$value;
                }
                $configs[$groupKey]['items'][$itemKey]['value'] = $value;
            }
        }

        return $configs;
    }
}
