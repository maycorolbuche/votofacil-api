<?php

namespace App\Helpers;

class JWT
{
    private static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function sign($payload)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $header = self::base64url_encode(json_encode($header));
        $payload = self::base64url_encode(json_encode($payload));

        $signature = hash_hmac('sha256', $header . "." . $payload, env('JWT_SECRET'), true);
        $signature = self::base64url_encode($signature);

        return $header . '.' . $payload . '.' . $signature;
    }

    public static function verify($jwt)
    {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            return false;
        }

        list($headerB64, $payloadB64, $signatureB64) = $tokenParts;

        $header = json_decode(self::base64url_decode($headerB64), true);
        $payload = json_decode(self::base64url_decode($payloadB64), true);

        $expectedSignature = self::base64url_encode(
            hash_hmac('sha256', $headerB64 . "." . $payloadB64, env('JWT_SECRET'), true)
        );

        if (!hash_equals($expectedSignature, $signatureB64)) {
            return false;
        }

        return $payload;
    }

    public static function getTokens()
    {
        if (!request()->header("authorization")) {
            return false;
        }

        $authHeader = request()->header("authorization");
        $pairs = explode(',', $authHeader);
        $result = [];
        foreach ($pairs as $pair) {
            [$key, $value] = explode('=', $pair, 2);
            $result[trim($key)] = trim($value);
        }

        return $result;
    }

    public static function isAdmin()
    {
        $tokens = self::getTokens();
        if (!$tokens) {
            return false;
        }

        $data = self::verify($tokens["admin"]);
        if ($data['type'] != 'admin') {
            return false;
        }

        return $data;
    }

    public static function isUser()
    {
        $tokens = self::getTokens();
        if (!$tokens) {
            return false;
        }

        $data = self::verify($tokens["user"]);
        if ($data['type'] != 'user') {
            return false;
        }

        return $data;
    }
}
