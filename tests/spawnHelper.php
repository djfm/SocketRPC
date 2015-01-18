<?php

namespace djfm\SocketRPC;

@ini_set('display_errors', 'on');

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$what = $argv[1];

if ($what === 'server') {
    $server = new Server();
    $remote = [
        'sent' => null,
        'multiple' => Tests\SocketRPCTest::$multiple
    ];

    $server->on('send', function ($data) use (&$remote, $server) {
        if ($data === 'kill') {
            $server->stop();
        } else {
            Tests\SocketRPCTest::onSend($remote, $data);
        }
    });

    $server->on('query', function ($data, $respond)  use (&$remote) {
        Tests\SocketRPCTest::onQuery($remote, $data, $respond);
    });

    $server->on('connection', function ($clientId) use (&$remote) {
        Tests\SocketRPCTest::onConnection($remote, $clientId);
    });

    $server->on('disconnected', function ($clientId) use (&$remote) {
        Tests\SocketRPCTest::onDisconnected($remote, $clientId);
    });

    $server->bind();
    echo $server->getAddress() . "\n";
    $server->run();
} else if ($what === 'client') {
    $address = $argv[2];
    $method = $argv[3];
    $client = new Client();
    $client->connect($address);
    Tests\SocketRPCTest::$method($client);
}
