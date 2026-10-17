<?php

namespace Hellotext\Tracking\Model;

class Configuration
{
    public static function isSafeApiRoot(string $value): bool
    {
        return in_array($value, [
            'https://api.hellotext.com/v1',
            'https://api.hellotext.com/v1/',
            'http://api.lvh.me:3100/v1',
            'http://api.lvh.me:3100/v1/',
            'http://api.lvh.me:3200/v1',
            'http://api.lvh.me:3200/v1/',
        ], true);
    }
}
