<?php

namespace djfm\SocketRPC;

use djfm\SocketRPC\Exception\CouldNotBindToAddressException;
use djfm\SocketRPC\Exception\NotListeningException;

class Server implements ServerInterface, EventEmitterInterface
{
    use EventEmitterTrait;

    private $server;
    private $address;
    private $running;

    private $readStreams = [];
    private $clientsById = [], $idsByClient = [], $clientId = 0;

    public function bind($port = null, $hostname = '127.0.0.1')
    {
        $this->port = $port;

        // if port is null, look for the first available one starting at 1337
        if ($port === null) {
            $this->port = 1337;
        }

        do {
            $this->address = 'tcp://' . $hostname . ':' . $this->port;
            $this->server = @stream_socket_server($this->address);

            if ($port === null) {
                if (!$this->server) {
                    ++$this->port;
                }
            } else {
                break;
            }

        } while (!$this->server);

        if (!$this->server) {
            throw new CouldNotBindToAddressException(sprintf("Server could not be started on `%s`.", $this->address));
        }

        stream_set_blocking($this->server, 0);

        $this->onRead($this->server, function ($stream) {
            $this->acceptClient($stream);
        });

        return $this;
    }

    public function getAddress()
    {
        return $this->address;
    }

    private function onRead($stream, $callback)
    {
        $this->readStreams[$stream] = [
            'stream' => $stream,
            'callback' => $callback,
            'dead' => false
        ];
    }

    private function offRead($stream)
    {
        $this->readStreams[$stream]['dead'] = true;

        return $this;
    }

    private function getReadStreamsArray()
    {
        return array_map(function ($streamHolder) {
            return $streamHolder['stream'];
        }, $this->readStreams);
    }

    private function acceptClient($server)
    {
        $client = stream_socket_accept($server);
        if ($client) {
            stream_set_blocking($client, 0);

            $clientId = $this->clientId++;
            $this->clientsById[$clientId] = $client;
            $this->idsByClient[$client] = $clientId;

            $parser = new StreamParser();

            $parser->on('data', function ($data) use ($client, $clientId) {
                $data = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($data) && isset($data['type'])) {
                        $type = $data['type'];
                        $payload = array_key_exists('data', $data) ? $data['data'] : null;
                        if ($type === 'send') {
                            $this->emit('send', $payload, $clientId);
                        } else if ($type === 'query') {
                            $respondCalled = false;
                            $respond = function ($reply) use ($client, &$respondCalled) {
                                if ($respondCalled) {
                                    // one response per request!
                                    return;
                                }
                                $respondCalled = true;
                                fwrite($client, StreamParser::buildRequestString(json_encode($reply)));
                            };
                            $this->emit('query', $payload, $respond, $clientId);
                            if (!$respondCalled) {
                                $respond(null);
                            }
                        }
                    }
                }
            });

            $this->onRead($client, function ($client) use ($parser) {
                $data = stream_get_contents($client);
                // We got data
                if ('' !== $data && false !== $data) {
                    $parser->consume($data);
                }

                // The connection is dead
                if ('' === $data || false === $data || !is_resource($client) || feof($client)) {
                    $this->offRead($client);
                }
            });

            $this->emit('connection', $clientId);
        }
    }

    public function run()
    {
        if (!$this->server) {
            throw new NotListeningException("Server doesn't seem to be listening, did you call bind?");
        }

        $this->running = true;

        $this->emit('run');

        while ($this->running) {
            $read = $this->getReadStreamsArray();
            $write = [];
            $except = [];
            stream_select($read, $write, $except, 0, 200000);

            foreach ($read as $readStream) {
                $this->readStreams[$readStream]['callback']($readStream);
                if ($this->readStreams[$readStream]['dead']) {
                    unset($this->readStreams[$readStream]);
                    $clientId = $this->idsByClient[$readStream];
                    unset($this->idsByClient[$readStream]);
                    unset($this->clientsById[$clientId]);
                    $this->emit('disconnected', $clientId);
                }
            }

            $this->emit('tick');
        }

        foreach ($this->getReadStreamsArray() as $stream) {
            stream_socket_shutdown($stream, STREAM_SHUT_RDWR);
        }

        $this->readStreams = [];
        $this->clientsById = [];
        $this->idsByClient = [];

        $this->emit('stopped');
    }

    public function stop()
    {
        $this->running = false;
        $this->emit('stop');

        return $this;
    }
}
