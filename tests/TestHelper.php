<?php

namespace djfm\SocketRPC\Tests;

class TestHelper
{
    public static function makeBigString($sizeInMB = 5, $pattern = 'abcdefgh')
    {
        $str = '';
        $len = $sizeInMB * 1024 * 1024 / strlen($pattern);
        for ($i = 0; $i < $len ; ++$i) {
            $str .= $pattern;
        }
        return $str;
    }
}
