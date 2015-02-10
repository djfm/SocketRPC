<?php

namespace djfm\SocketRPC;

use djfm\SocketRPC\Exception\StreamParserException;

class StreamParser implements EventEmitterInterface
{
    use EventEmitterTrait;

    const HEADER_SIZE = 16;

    const BEFORE_REQUEST = 0;
    const IN_REQUEST = 1;

    private $state = self::BEFORE_REQUEST;
    private $buffer = '';
    private $body = '';

    public function consume($data)
    {
        if (!is_string($data)) {
            throw new StreamParserException('Parser can only consume strings.');
        }

        $this->buffer .= $data;

        do {
            $continue = false;

            if (self::BEFORE_REQUEST === $this->state) {
                if (strlen($this->buffer) >= self::HEADER_SIZE) {
                    $header = trim(substr($this->buffer, 0, self::HEADER_SIZE));
                    $this->contentLength = (int)$header;
                    $this->state = self::IN_REQUEST;
                    $continue = true;
                }
            }

            if (self::IN_REQUEST === $this->state) {
                if (strlen($this->buffer) >= $this->contentLength) {
                    $this->body = substr($this->buffer, self::HEADER_SIZE, $this->contentLength - self::HEADER_SIZE);
                    $this->emit('data', $this->body);
                    $this->buffer = substr($this->buffer, $this->contentLength);
                    $this->state = self::BEFORE_REQUEST;
                    $continue = true;
                }
            }
        } while ($continue);


        return $this;
    }

    public function hasBody()
    {
        return '' !== $this->body;
    }

    public function getAndClearBody()
    {
        $body = $this->body;
        $this->body = '';
        return $body;
    }

    public static function buildRequestString($payloadString)
    {
        $contentLength = str_pad(strlen($payloadString) + self::HEADER_SIZE, self::HEADER_SIZE);
        return $contentLength . $payloadString;
    }
}
