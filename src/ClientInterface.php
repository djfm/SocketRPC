<?php

namespace djfm\SocketRPC;

interface ClientInterface
{
    public function connect($serverAddress);
    public function send($data);
    public function query($data);
}
