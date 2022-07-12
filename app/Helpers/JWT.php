<?php

namespace App\Helpers;

class JWT
{

    public static function sign($payload)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $header = json_encode($header);
        $payload = json_encode($payload);

        $header = base64_encode($header);
        $payload = base64_encode($payload);

        $sign = hash_hmac('sha256', $header . "." . $payload, env('JWT_SECRET'), true);
        $sign = base64_encode($sign);

        return $header . '.' . $payload . '.' . $sign;
    }

    public static function verify($jwt)
    {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) < 3) {
            return false;
        }
        $payload = json_decode(base64_decode($tokenParts[1]), true);

        if ($jwt !== JWT::sign($payload)) {
            return false;
        }
        return $payload;

    }

}
