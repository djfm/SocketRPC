<?php

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use djfm\SocketRPC\Server;

$server = new Server();

$server->bind(1337);

echo sprintf("Server ready to accept connections on `%s`.\n", $server->getAddress());

$server->on('connection', function ($clientId) {
    echo "Welcome, client #$clientId!\n";
})->on('disconnected', function ($clientId) {
    echo "Bye, client #$clientId!\n";
})->on('send', function ($data) {
    var_dump($data);
})->on('query', function ($query, $respond) {
    var_dump($query);
    $respond(42);
});

$server->run();
