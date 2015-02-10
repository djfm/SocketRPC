<?php

namespace djfm\SocketRPC;

use djfm\SocketRPC\Exception\CouldNotConnectToServerException;
use djfm\SocketRPC\Exception\ConnectionClosedByServerException;
use djfm\SocketRPC\Exception\CouldNotSendDataException;

class Client implements ClientInterface
{
    private $serverAddress;
    private $parser;

    public function __construct()
    {
        $this->parser = new StreamParser();
    }

    private function checkConnected()
    {
        if (!$this->socket || feof($this->socket)) {
            throw new CouldNotConnectToServerException(sprintf('Could not connect to server at `%s`.', $this->serverAddress));
        }

        return $this;
    }

    public function connect($serverAddress)
    {
        $this->serverAddress = $serverAddress;

        $this->socket = stream_socket_client($this->serverAddress);

        $this->checkConnected();

        stream_set_blocking($this->socket, 0);

        return $this;
    }

    public function disconnect()
    {
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        return $this;
    }

    private function transmit($data)
    {
        $this->checkConnected();

        $payload = StreamParser::buildRequestString(json_encode($data));

        $pos = 0;
        $chunkSize = 4096;
        $maxAttempts = 3;
        $attempt = 0;
        while ($pos < strlen($payload)) {
            $sent = @stream_socket_sendto($this->socket, substr($payload, $pos, $chunkSize));
            if ($sent < 0) {
                if ($attempt < $maxAttempts) {
                    sleep(1);
                    $attempt++;
                } else {
                    throw new CouldNotSendDataException(sprintf('Failed to send `%d` bytes over the wire.', $chunkSize));
                }
            } else {
                $pos += $sent;
                $attempt = 0;
            }
        }

        fflush($this->socket);

        return $this;
    }

    public function send($data)
    {
        $this->transmit([
            'type' => 'send',
            'data' => $data
        ]);

        return $this;
    }

    public function query($data)
    {
        $this->transmit([
            'type' => 'query',
            'data' => $data
        ]);

        while (!$this->parser->hasBody()) {
            $read = [$this->socket];
            $write = [];
            $except = [];
            $count = stream_select($read, $write, $except, 1);
            if ($count > 0) {
                $data = stream_get_contents($this->socket);

                if ('' === $data || false === $data || !is_resource($this->socket) || feof($this->socket)) {
                    throw new ConnectionClosedByServerException();
                }

                $this->parser->consume($data);
            }
        }

        return json_decode($this->parser->getAndClearBody(), true);
    }
}
