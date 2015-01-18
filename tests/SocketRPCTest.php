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

    public static function setupBeforeClass()
    {
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

    // This is a handler for the remote server process started in setupBeforeClass
    public static function onSend(&$remote, $data)
    {
        $remote['sent'] = $data;
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
            }
        }
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
        $str = TestHelper::makeBigString(20);
        $md5 = md5($str);
        $this->assertEquals($md5, $this->query(['method' => 'md5', 'payload' => $str]));
    }
}
