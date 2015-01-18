<?php

namespace djfm\SocketRPC\Tests;

use PHPUnit_Framework_TestCase;

use djfm\SocketRPC\Server;
use djfm\SocketRPC\Client;

class SocketRPCTest extends PHPUnit_Framework_TestCase
{
    private static $serverProc;
    private static $serverAddress;
    private static $client;
    private static $remoteClientsSpawned = [];

    public static function setupBeforeClass()
    {
        /*self::$serverAddress = 'tcp://127.0.0.1:1337';
        self::$client = self::newClient();
        return;*/
        $spawnHelperPath = __DIR__ . DIRECTORY_SEPARATOR . 'spawnHelper.php';
        $command = PHP_BINARY . ' ' . $spawnHelperPath . ' ' . 'server';
        self::$serverProc = popen($command, 'r');
        self::$serverAddress = trim(fgets(self::$serverProc));
        self::$client = self::newClient();
    }

    public static function newClient()
    {
        return (new Client())->connect(self::$serverAddress);
    }

    public static function newRemoteClient($methodToRun)
    {
        $spawnHelperPath = __DIR__ . DIRECTORY_SEPARATOR . 'spawnHelper.php';
        $command = PHP_BINARY . ' ' . $spawnHelperPath . ' ' . 'client' . ' ' . self::$serverAddress . ' ' . $methodToRun;
        // need to store the handles, otherwise PHP waits for the command to exit.
        self::$remoteClientsSpawned[] = popen($command, 'r');
    }

    public static function waitForRemoteClientsToExit()
    {
        foreach (self::$remoteClientsSpawned as $client) {
            pclose($client);
        }
    }

    // This is a handler for the remote server process started in setupBeforeClass
    public static function onSend(&$remote, $data)
    {
        $remote['sent'] = $data;
        if ($data === 'md5ok') {
            ++$remote['multiple']['md5ok'];
        }
    }

    // This is a handler for the remote server process started in setupBeforeClass
    public static function onQuery(&$remote, $data, $respond)
    {
        if ($data === 'echo') {
            $respond('echo echo');
        } else if ($data === 'getSent') {
            $respond($remote['sent']);
        } else if (is_array($data) && isset($data['method'])) {
            $method = $data['method'];

            if ($method === 'md5') {
                $respond(md5($data['payload']));
            } else if ($method === 'resetMultiple') {
                $remote['multiple'] = self::$multiple;
                $respond($remote['multiple']);
            } else if ($method === 'getMultiple') {
                $respond($remote['multiple']);
            }
        }
    }

    // This is a handler for the remote server process started in setupBeforeClass
    public static function onConnection(&$remote)
    {
        ++$remote['multiple']['connections'];
        ++$remote['multiple']['simultaneousConnections'];

        if ($remote['multiple']['simultaneousConnections'] > $remote['multiple']['maxSimultaneousConnections']) {
            $remote['multiple']['maxSimultaneousConnections'] = $remote['multiple']['simultaneousConnections'];
        }
    }

    // This is a handler for the remote server process started in setupBeforeClass
    public static function onDisconnected(&$remote)
    {
        --$remote['multiple']['simultaneousConnections'];
    }

    public static function tearDownAfterClass()
    {
        self::$client->send('kill');
    }

    public function send($data)
    {
        return self::$client->send($data);
    }

    public function query($data)
    {
        return self::$client->query($data);
    }

    public function testQuery()
    {
        $this->assertEquals('echo echo', $this->query('echo'));
    }

    public function testSend()
    {
        $this->assertEquals(null, $this->query('getSent'));
        $this->send('hello');
        $this->assertEquals('hello', $this->query('getSent'));
    }

    public function testBigQuery()
    {
        $str = TestHelper::makeBigString(10);
        $md5 = md5($str);
        $this->assertEquals($md5, $this->query(['method' => 'md5', 'payload' => $str]));
    }

    public static $multiple = [
        'connections' => 0,
        'simultaneousConnections' => 0,
        'maxSimultaneousConnections' => 0,
        'md5ok' => 0
    ];

    public static function computeMD5AndSleep($client)
    {
        $str = self::$mediumString;
        $md5 = $client->query(['method' => 'md5', 'payload' => $str]);
        if ($md5 === md5(self::$mediumString)) {
            $client->send('md5ok');
        }
        sleep(1);
    }

    private static $mediumString;

    /**
     * Here we spawn X clients, each asking the server
     * to compute the md5 of a big string and send it back.
     * If the operation is successful, the client sends
     * 'md5ok' to the server, who increments a counter.
     * Afterwards, we check that the server did indeed receive
     * the X 'md5ok' messages.
     */
    public function testMultipleClients()
    {
        self::$mediumString = TestHelper::makeBigString(2);

        $this->assertEquals(self::$multiple, $this->query(['method' => 'resetMultiple']));
        $parallel = 50;

        for ($i = 0; $i < $parallel; ++$i) {
            self::newRemoteClient('computeMD5AndSleep');
        }

        static::waitForRemoteClientsToExit();

        $multiple = $this->query(['method' => 'getMultiple']);
        $this->assertEquals($parallel, $multiple['connections']);
        $this->assertEquals($parallel, $multiple['maxSimultaneousConnections']);
        $this->assertEquals($parallel, $multiple['md5ok']);
    }
}
