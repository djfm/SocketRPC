<?php

namespace djfm\SocketRPC;

interface ServerInterface
{
    public function bind($port = null, $hostname = '127.0.0.1');
    public function getAddress();
    public function run();
    public function stop();
}
