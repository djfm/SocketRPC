<?php

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use djfm\SocketRPC\Client;

$client = new Client();

$client->connect("tcp://127.0.0.1:1337")->send('hello');

$response = $client->query('41 + 1 ?');

echo "Response: $response\n";
