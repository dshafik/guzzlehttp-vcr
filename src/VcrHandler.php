<?php
namespace Dshafik\GuzzleHttp;

use \GuzzleHttp\Psr7\Response;

/**
 * guzzlehttp-vcr middleware
 *
 * Records and automatically replays responses on subsequent requests
 * for unit testing
 * 
 * @package Dshafik\GuzzleHttp
 */
class VcrHandler
{
    const CONFIG_ONLY_ENCODE_BINARY = 'only_encode_binary';

    /**
     * @var string
     */
    protected $cassette;

    /**
     * Configuration values
     *
     * @var array
     */
    protected static $config;

    /**
     * @param string $cassette fixture path 
     * @return \GuzzleHttp\HandlerStack
     */
    public static function turnOn($cassette, array $config = null)
    {
        if (! is_null($config)) {
            static::$config = $config;
        }

        if (!file_exists($cassette)) {
            $handler = \GuzzleHttp\HandlerStack::create();
            $handler->after('allow_redirects', new static($cassette), 'vcr_recorder');
            return $handler;
        } else {
            $responses = self::decodeResponses($cassette);

            $queue = [];
            $class = new \ReflectionClass(\GuzzleHttp\Psr7\Response::class);
            foreach ($responses as $response) {
                $queue[] = $class->newInstanceArgs($response);
            }

            return \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler($queue));
        }
    }

    /**
     * Constructor
     * 
     * @param string $cassette fixture path
     */
    protected function __construct($cassette)
    {
        $this->cassette = $cassette;
    }

    /**
     * Returns configuration value by name
     *
     * @param string $name Name of configuration value
     * 
     * @return null|mixed
     */
    protected static function getConfig($name = null)
    {
        if (is_null($name)) {
            return static::$config;
        }

        if (! static::$config || ! isset(static::$config[$name])) {
            return null;
        }

        return $config[$name];
    }

    /**
     * Returns True if response content is binary
     *
     * @param GuzzleHttp\Psr7\Response $response Response object
     * 
     * @return boolean
     */
    protected static function isBinary(Response $response)
    {
        return strpos($response->getContentType(), 'application/x-gzip') !== false
            || $response->getHeader('Content-Transfer-Encoding') == 'binary';
    }

    /**
     * Resolve an object Response from given value. 
     * If value is already a Response returns value.
     * If value is an array attempts to create a Response object
     * otherwise throws an exception.
     *
     * @param Response|array $value
     * 
     * @throws \InvalidArgumentException
     * 
     * @return void
     */
    protected static function ensureResponse($value)
    {
        if ($value instanceof Response) {
            return $value;
        }

        if (! is_array($value)) {
            throw new \InvalidArgumentException('Invalid value for response');
        }

        return new Response(
            $value['status'],
            $value['headers'],
            $value['body'],
            $value['version'],
            $value['reason']
        );
    }

    /**
     * Encode body content from given response.
     *
     * @param Response|array $response Response value
     * 
     * @return string
     */
    protected static function encodeBodyFrom($response)
    {
        $response = static::ensureResponse($response);
        $body = (string) $response->getBody();

        if (static::getConfig(self::CONFIG_ONLY_ENCODE_BINARY)
            && ! static::isBinary($response)
        ) {
            return $body;
        }

        return \base64_encode($body);
    }

    /**
     * Decode body content from given response.
     *
     * @param Response|array $response Response value
     * 
     * @return string
     */
    protected static function decodeBodyFrom($response)
    {
        $response = static::ensureResponse($response);
        $body = (string) $response->getBody();

        if (static::getConfig(self::CONFIG_ONLY_ENCODE_BINARY)
            && ! static::isBinary($response)
        ) {
            return $body;
        }

        return \base64_decode($body);
    }

    /**
     * Decodes every responses body from base64
     *
     * @param $cassette
     * @return array
     */
    protected static function decodeResponses($cassette)
    {
        $responses = json_decode(file_get_contents($cassette), true);

        array_walk(
            $responses, function (&$response) {
                $response['body'] = static::decodeBodyFrom($response);
            }
        );

        return $responses;
    }

    /**
     * Handle the request/response
     *
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (\Psr\Http\Message\RequestInterface $request, array $config) use ($handler) {
            return $handler($request, $config)->then(
                function (\Psr\Http\Message\ResponseInterface $response) use ($request) {
                    $responses = [];
                    if (file_exists($this->cassette)) {
                        //No need to base64 decode body of response here.
                        $responses = json_decode(file_get_contents($this->cassette), true);
                    }
                    $cassette = $response->withAddedHeader('X-VCR-Recording', time());
                    $responses[] = [
                        'status' =>  $cassette->getStatusCode(),
                        'headers' => $cassette->getHeaders(),
                        'body' => static::encodeBodyFrom($cassette),
                        'version' => $cassette->getProtocolVersion(),
                        'reason' => $cassette->getReasonPhrase()
                    ];
                    
                    file_put_contents($this->cassette, json_encode($responses, JSON_PRETTY_PRINT));
                    return $response;
                },
                function (\Exception $reason) {
                    return new \GuzzleHttp\Promise\RejectedPromise($reason);
                }
            );
        };
    }
}
