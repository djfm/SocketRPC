# SocketRPC

SocketRPC offers a convenient way to share structured data between a CLI php server and multiple clients.
The server is async, inspired by the great work at [reactphp](https://github.com/reactphp).

Think HTTP, without the structure. It's convenient. No POST, no GET, just JSON data and a notion of whether the client wants a reply or not.
The rest of the protocol is for you to define at your leisure.

## Disclosure

This is an early version, don't use it for anything too serious.
I needed some cross platform way to implement functionality like that of `msg_queue` for IPC communication in PHP, so I made this thing.

## Examples

Below is an example server:
```php
<?php

use djfm\SocketRPC\Server;

$server = new Server();

$server->bind(1337);

echo sprintf("Server ready to accept connections on `%s`.\n", $server->getAddress());

$server->on('connection', function ($clientId) {
    echo "Welcome, client #$clientId!\n";
})->on('disconnected', function ($clientId) {
    echo "Bye, client #$clientId!\n";
})->on('send', function ($data, $clientId) {
    // client sends data to us, but does not expect a reply,
    // think logs, monitoring...
    var_dump($data);
})->on('query', function ($query, $respond, $clientId) {
    // client sends us something, and wants a response
    var_dump($query);
    $respond(42);
});

// this loops never returns, set things up before :)
$server->run();
```

And here is a client:
```php
<?php

use djfm\SocketRPC\Client;

$client = new Client();

$client->connect("tcp://127.0.0.1:1337");

// We just need to tell something to the server, don't wait for a reply
$client->send('hello');

// This is important, expect a reply
$response = $client->query('41 + 1 ?');

echo "Response: $response\n";
```

The examples can be found in the `examples` folder in this repo.

## Design

The server side is asynchronous, so it can handle many connections smoothly.

But on the client, requests are synchronous, because this is PHP, and the async paradigm quickly becomes tedious.

You can transmit any payload over the wire that is JSON serializable.
If you need to send binary data, `base64_encode` it first.

## Tests

To check that everything is OK on your platform, run:
```bash
phpunit tests
```
