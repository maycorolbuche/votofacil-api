<?php

namespace App\Helpers;

class Cache
{
    protected static $cachePath = __DIR__ . '/../../storage/cache/';

    // Gera o caminho do arquivo de cache baseado na chave
    protected static function getCacheFile($key)
    {
        return self::$cachePath . md5($key) . '.cache';
    }

    // Salva um valor no cache
    public static function set($key, $value, $ttl = 3600)
    {
        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0777, true);
        }
        $data = [
            'expires_at' => time() + $ttl,
            'value' => $value
        ];
        file_put_contents(self::getCacheFile($key), serialize($data));
    }

    // Recupera um valor do cache
    public static function get($key, $value = null)
    {
        $file = self::getCacheFile($key);
        if (!file_exists($file)) {
            return $value;
        }
        $data = unserialize(file_get_contents($file));
        if (!isset($data['expires_at']) || $data['expires_at'] < time()) {
            unlink($file);
            return $value;
        }
        return $data['value'];
    }

    // Remove um item do cache
    public static function forget($key)
    {
        $file = self::getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Limpa todo o cache
    public static function clear()
    {
        if (!is_dir(self::$cachePath)) {
            return;
        }
        foreach (glob(self::$cachePath . '*.cache') as $file) {
            unlink($file);
        }
    }
}
