<?php
namespace Dshafik\GuzzleHttp\Tests;

class VcrHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        \GuzzleHttp\Tests\Server::stop();
        \GuzzleHttp\Tests\Server::start();
    }
    
    /**
     * @dataProvider recordingProvider
     */
    public function testRecording($name, $requests, $responses)
    {
        $this->setName($name);
        $this->assertGreaterThanOrEqual(sizeof($requests), sizeof($responses));
        
        \GuzzleHttp\Tests\Server::enqueue($responses);
        $cassette = __DIR__ . '/fixtures/temp/' . str_replace([" with data set #", "test", " "], ["-", "", "-"], $this->getName()) . '.json';
        
        $responses = [];
        $vcr = \Dshafik\GuzzleHttp\VcrHandler::turnOn($cassette);
        $this->assertInstanceOf(\GuzzleHttp\HandlerStack::class, $vcr);
        $client = new \GuzzleHttp\Client(['handler' => $vcr]);
        foreach ($requests as $key => $request) {
            try {
                $response = $client->send($request);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
            }
            $this->assertEmpty($response->getHeader('X-VCR-Recording'));
            $responses[$key] = $response;
        }

        $vcr = \Dshafik\GuzzleHttp\VcrHandler::turnOn($cassette);
        $this->assertInstanceOf(\GuzzleHttp\HandlerStack::class, $vcr);
        $client = new \GuzzleHttp\Client(['handler' => $vcr]);
        foreach ($requests as $key => $request) {
            try {
                $recording = $client->send($request);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $recording = $e->getResponse();
            }
            
            $this->assertTrue(ctype_digit($recording->getHeader('X-VCR-Recording')[0]));

            $this->assertEquals($responses[$key]->getStatusCode(), $recording->getStatusCode());
            foreach ($responses[$key]->getHeaders() as $header => $value) {
                $this->assertEquals($value, $recording->getHeader($header));
            }

            $this->assertEquals((string) $responses[$key]->getBody(), (string) $recording->getBody());
        }
    }
    
    public function recordingProvider()
    {
        return [
            [
                'name' => 'Record single request',
                'requests' => [new \GuzzleHttp\Psr7\Request('GET', \GuzzleHttp\Tests\Server::$url)],
                'responses' => [new \GuzzleHttp\Psr7\Response(200, [], "Hello World")]
            ],
            [
                'name' => 'Record multiple requests',
                'requests' => [
                    new \GuzzleHttp\Psr7\Request('GET', \GuzzleHttp\Tests\Server::$url),
                    new \GuzzleHttp\Psr7\Request('POST', \GuzzleHttp\Tests\Server::$url)
                ],
                'responses' => [
                    new \GuzzleHttp\Psr7\Response(200, [], "Hello World"),
                    new \GuzzleHttp\Psr7\Response(301, ['Location' => '/test']),
                    new \GuzzleHttp\Psr7\Response(404),
                ]
            ],
            [
                'name' => 'POST request',
                'requests' => [
                    new \GuzzleHttp\Psr7\Request('POST', \GuzzleHttp\Tests\Server::$url, [], 'Hello World')
                ],
                'responses' => [
                    new \GuzzleHttp\Psr7\Response(202),
                ]
            ],
            [
                'name' => 'Multiple Methods',
                'requests' => [
                    new \GuzzleHttp\Psr7\Request('GET', \GuzzleHttp\Tests\Server::$url),
                    new \GuzzleHttp\Psr7\Request('POST', \GuzzleHttp\Tests\Server::$url, [], 'Hello World')
                ],
                'responses' => [
                    new \GuzzleHttp\Psr7\Response(200),
                    new \GuzzleHttp\Psr7\Response(200, [], 'Goodbye Moon'),
                ]
            ],
        ];
    }
    
    public function testExisting()
    {
        $vcr = \Dshafik\GuzzleHttp\VcrHandler::turnOn(__DIR__ . '/fixtures/test-existing.json');
        
        $client = new \GuzzleHttp\Client(['handler' => $vcr]);
        $response = $client->get('/test');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('X-VCR-Recording', $response->getHeaders());
        $this->assertEquals("1440121471", $response->getHeader('X-VCR-Recording')[0]);
        $this->assertEquals('Hello World', (string) $response->getBody());
    }

    public static function setupBeforeClass()
    {
        if (!file_exists(__DIR__ . "/fixtures/temp")) {
            mkdir(__DIR__ . "/fixtures/temp", 0777, true);
            return;
        }
        
        foreach (glob(__DIR__ . "/fixtures/temp/*.json") as $file) {
            unlink($file);
        }
    }
}
