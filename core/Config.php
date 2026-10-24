<?php

namespace Core;

class Config
{
    private static array $data = [];

    public static function init(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $key, $default = null)
    {
        $value = self::$data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}





