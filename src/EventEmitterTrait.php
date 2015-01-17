<?php

namespace djfm\SocketRPC;

trait EventEmitterTrait
{
    private $callbacks = [];

    public function on($event, $listener)
    {
        if (!isset($this->callbacks[$event])) {
            $this->callbacks[$event] = [];
        }

        $this->callbacks[$event][] = [
            'once' => false,
            'listener' => $listener
        ];

        return $this;
    }

    public function once($event, $listener)
    {
        if (!isset($this->callbacks[$event])) {
            $this->callbacks[$event] = [];
        }

        $this->callbacks[$event][] = [
            'once' => true,
            'listener' => $listener
        ];

        return $this;
    }

    public function off($event, $listener = null)
    {
        if (isset($this->callbacks[$event])) {
            if (!$listener) {
                unset($this->callbacks[$event]);
            } else {
                for ($i = 0; $i < count($this->callbacks[$event]); ++$i) {
                    if ($this->callbacks[$event][$i]['listener'] === $listener) {
                        unset($this->callbacks[$event][$i]);
                        break;
                    }
                }
            }
        }

        return $this;
    }

    public function emit()
    {
        $args = func_get_args();
        if (count($args) > 0) {
            $event = array_shift($args);
            if (isset($this->callbacks[$event])) {
                foreach ($this->callbacks[$event] as $pos => $callback) {
                    call_user_func_array($callback['listener'], $args);
                    if ($callback['once']) {
                        unset($this->callbacks[$event][$pos]);
                    }
                }
            }
        }

        return $this;
    }
}
