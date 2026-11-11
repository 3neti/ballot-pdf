<?php

namespace LBHurtado\BallotPdf\Support;

final class CanonicalJson
{
    /** @param mixed $value */
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** @return mixed */
    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::normalize($value[$key]);
        }
        return $result;
    }
}
