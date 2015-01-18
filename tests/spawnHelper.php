<?php

namespace djfm\SocketRPC;

@ini_set('display_errors', 'on');

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$what = $argv[1];

if ($what === 'server') {
    $server = new Server();
    $remote = [
        'sent' => null
    ];

    $server->on('send', function ($data) use (&$remote) {
        if ($data === 'kill') {
            die();
        } else {
            Tests\SocketRPCTest::onSend($remote, $data);
        }
    });

    $server->on('query', function ($data, $respond)  use (&$remote) {
        Tests\SocketRPCTest::onQuery($remote, $data, $respond);
    });

    $server->bind();
    echo $server->getAddress() . "\n";
    $server->run();
}
