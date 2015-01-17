<?php

namespace djfm\SocketRPC;

interface EventEmitterInterface
{
    public function on($event, $listener);
    public function once($event, $listener);
    public function off($event, $listener = null);
    public function emit(/* $event, [$arg1], [$arg2], [...] */);
}
