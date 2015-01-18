<?php

namespace djfm\SocketRPC\Tests;

use djfm\SocketRPC\StreamParser;
use PHPUnit_Framework_TestCase;

class StreamParserTest extends PHPUnit_Framework_TestCase
{
    public function testEncodingOfRequest()
    {
        $this->assertEquals('26              1234567890', StreamParser::buildRequestString('1234567890'));
    }

    public function testEventBasedDecoding()
    {
        $expectedBody = 'hello!';
        $checked = false;
        $parser = new StreamParser();
        $parser->on('data', function ($body) use ($expectedBody, &$checked) {
            $this->assertEquals($expectedBody, $body);
            $checked = true;
        });
        $parser->consume(StreamParser::buildRequestString($expectedBody));
        $this->assertTrue($checked);
    }

    public function testNonEventBasedDecoding()
    {
        $expectedBody = 'hello!';
        $parser = new StreamParser();
        $parser->consume(StreamParser::buildRequestString($expectedBody));
        $this->assertEquals(true, $parser->hasBody());
        $this->assertEquals($expectedBody, $parser->getAndClearBody());
        $this->assertEquals(false, $parser->hasBody());
    }

    public function testSuccessiveChunkedDecoding()
    {
        $bodies = ['hello', 'this is secod', 'and third', 'well, that should be enough :)'];
        $requests = str_split(implode('', array_map(function ($body) {
            return StreamParser::buildRequestString($body);
        }, $bodies)), 7);

        $parser = new StreamParser();
        $parser->on('data', function ($body) use (&$bodies) {
            $this->assertEquals(array_shift($bodies), $body);
        });

        foreach ($requests as $chunk) {
            $parser->consume($chunk);
        }

        // make sure we got all of our requests
        $this->assertEmpty($bodies);
    }

    public function testBigRequestDecoding()
    {
        $expectedBody = TestHelper::makeBigString(5);
        $parser = new StreamParser();
        $checked = false;
        $parser->on('data', function ($body) use ($expectedBody, &$checked) {
            $this->assertEquals($expectedBody, $body);
            $checked = true;
        });
        $parser->consume(StreamParser::buildRequestString($expectedBody));
        $this->assertTrue($checked);
    }
}
