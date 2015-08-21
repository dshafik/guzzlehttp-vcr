[![License](https://img.shields.io/github/license/mashape/apistatus.svg)](https://github.com/dshafik/guzzlehttp-vcr)[![Travis CI Status](https://travis-ci.org/dshafik/guzzlehttp-vcr.svg?branch=master)](https://travis-ci.org/dshafik/guzzlehttp-vcr) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dshafik/guzzlehttp-vcr/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dshafik/guzzlehttp-vcr/?branch=master)

# Guzzle VCR

Based on the idea of [PHP•VCR](http://php-vcr.github.io), this Guzzle Middleware will record responses and replay them in response to subsequent requests.

This middleware is simplistic in that it will simply replay the responses in order in response to _any_ requests. This is handy for testing clients that have time-based authentication and need to generate dynamic requests but still want predictable responses to test response handling.

## Installation

To add to your project, use `composer`:

```
$ composer require dshafik/guzzlehttp-vcr
```

## Usage

It's use is _similar_ to Guzzles `\GuzzleHttp\Handler\MockHandler`, and in fact uses the `MockHandler` to replay the recorded requests. Calling the `Dshafik\GuzzleHttp\VcrHandler::turnOn()` method will return either an instance of the standard `GuzzleHttp\HandlerStack` with either the `VcrHandler` or `MockHandler` (with the requests loaded) added as middleware.

You then pass the handler in as the `GuzzleHttp\Client` handler option, either in the constructor, or with the individual request.
 
The recording is halted on script termination, or the next time `VcrHandler::turnOn()` is called _for that recording_.

```php
<?php
class ApiClientTest {
    public function testSomething() {
        $vcr = \Dshafik\GuzzleHttp\VcrHandler::turnOn(__DIR__ . '/fixtures/somethingtest.json');
        $client = new \GuzzleHttp\Client(['handler' => $vcr]);

        $client->get('/test');
    }
}
?>
```

In this example, if the fixture exists, it will be used — using `MockHandler` — in response to _any_ requests made until it runs out of possible responses. Once it runs out of responses it will throw an `\OutOfBoundsException` exception on the next request.

## Fixtures

Fixtures are simple JSON files that you can edit or create by hand:

```json
[
    {
        "body": "Hello World",
        "headers": {
            "Connection": [
                "keep-alive"
            ],
            "Date": [
                "Fri, 21 Aug 2015 01:10:34 GMT"
            ],
            "Transfer-Encoding": [
                "chunked"
            ],
            "X-VCR-Recording": [
                "1440119434"
            ]
        },
        "reason": "OK",
        "status": 200,
        "version": "1.1"
    }
]
```

The only difference between the recording and the original response is the addition of an `X-VCR-Recording` header that contains the UNIX timestamp of the time it was recorded.

## Testing

The unit tests for this library use Guzzles built-in Node.js server, this means that you _must_ install with the `--prefer-source` flag, otherwise test sources are not included.
 
To run the unit tests simply run `phpunit` in the root of the repository:
 
```sh
 $ phpunit
 PHPUnit 4.8.5 by Sebastian Bergmann and contributors.
 
 Runtime:	PHP 5.6.10 with Xdebug 2.3.3
 Configuration:	/Users/dshafik/src/guzzlehttp-vcr/phpunit.xml.dist
 
 ....
 
 Time: 2.94 seconds, Memory: 9.00Mb
 
 OK (5 tests, 62 assertions)
```
