<?php
namespace Ntuple\Synctree\Util;

use InvalidArgumentException;

class ValidationUtil
{
    /**
     * @param $key
     * @return int|string
     */
    public static function validateArrayKey($key)
    {
        if (!is_string($key) && !is_int($key)) {
            throw new InvalidArgumentException('Not a string or integer type');
        }

        return $key;
    }

    /**
     * @param $value
     * @return string
     */
    public static function isConvertStringType($value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Not a string or integer or float type');
        }

        return (string) $value;
    }
}