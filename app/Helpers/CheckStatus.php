<?php

namespace App\Helpers;

use App\Helpers\JWT;
use App\Models\Room;
use App\Models\Device;

class CheckStatus
{
    public static function checkRoom($id)
    {
        $room = Room::select(['id', 'code', 'is_active'])->firstWhere('id', $id);

        if (!$room) {
            return ['error' => 'Sala não encontrada!'];
        }

        if ($room->is_active == false) {
            return ['error' => 'Sala "' . $room->code . '" não está ativa!'];
        }

        return $room;
    }
    public static function checkDevice($id)
    {
        $device = Device::select(['id', 'room_id', 'status'])->firstWhere('id', $id);

        if (!$device) {
            return ['error' => 'Dispositivo não encontrado!'];
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
            return ['error' => 'Token inválido!'];
        }

        $room = self::checkRoom($authToken['room_id']);
        if ($room['error']) {
            return ['error' => $room['error']];
        }

        return ['room_id' => $room->id, 'code' => $room->code, 'is_active' => $room->is_active];
    }

    public static function device()
    {
        $authToken = JWT::isUser();

        if (!$authToken) {
            return ['error' => 'Token inválido!'];
        }

        $room = self::checkRoom($authToken['room_id']);
        if ($room['error']) {
            return ['error' => $room['error']];
        }

        $device = self::checkDevice($authToken['device_id']);
        return ['room_id' => $room->id, 'code' => $room->code, 'is_active' => $room->is_active, 'device_id' => $device->id, 'status' => $device->status];
    }
}
