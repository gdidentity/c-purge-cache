<?php

namespace CPurgeCache;

class Settings
{
    public static $optionName = 'c-purge-cache';

    public static function get()
    {
        return get_option(self::$optionName);
    }

    public static function checked($field)
    {
        return isset($field);
    }
}
