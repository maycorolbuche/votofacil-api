<?php

namespace App\Helpers;

use App\Helpers\JWT;
use App\Models\Room;
use App\Models\Device;

class CheckStatus
{
    public static function checkRoom($id)
    {
        $room = Room::firstWhere('id', $id);

        if (!$room) {
            return ['error' => 'Sala não encontrada!'];
        }

        if ($room->is_active == false) {
            return ['error' => 'Sala não está ativa!'];
        }

        return $room;
    }
    public static function checkDevice($id)
    {
        $device = Device::with('room')->firstWhere('id', $id);

        if (!$device) {
            return response()->json(['error' => 'Dispositivo não encontrado!'], 500);
        }

        if ($device->status == 'disapproved') {
            return ['error' => 'Dispositivo não autorizado!'];
        }

        return $device;
    }

    public static function room()
    {
        $authToken = JWT::isAdmin();

        if (!$authToken) {
            return response()->json(['error' => 'Token inválido!'], 500);
        }

        $room = self::checkRoom($authToken['room_id']);
        if ($room['error']) {
            return response()->json(['error' => $room['error']], 500);
        }

        return $room;
    }

    public static function device()
    {
        $authToken = JWT::isUser();

        if (!$authToken) {
            return response()->json(['error' => 'Token inválido!'], 500);
        }

        $room = self::checkRoom($authToken['room_id']);
        if ($room['error']) {
            return response()->json(['error' => $room['error']], 500);
        }

        $device = self::checkDevice($authToken['device_id']);
        return $device;
    }
}
