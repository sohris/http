<?php

namespace Sohris\Http\Router\Message;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use React\Http\Io\AbstractRequest;
use React\Http\Io\BufferedBody;
use React\Http\Io\ReadableBodyStream;
use React\Stream\ReadableStreamInterface;

final class Request extends AbstractRequest implements RequestInterface
{
    public array $REQUEST = [];
    public array $SESSION = [];
    public array $FILES = [];

    public function __construct(
        $method,
        $url,
        array $headers = array(),
        $body = '',
        $version = '1.1'
    ) {
        if (\is_string($body)) {
            $body = new BufferedBody($body);
        } elseif ($body instanceof ReadableStreamInterface && !$body instanceof StreamInterface) {
            $body = new ReadableBodyStream($body);
        } elseif (!$body instanceof StreamInterface) {
            throw new \InvalidArgumentException('Invalid request body given');
        }

        parent::__construct($method, $url, $headers, $body, $version);
    }

    public static function fromRequest(RequestInterface $request)
    {
        return new self($request->getMethod(),$request->getUri(),$request->getHeaders(), $request->getBody(), $request->getProtocolVersion());
    }
}
