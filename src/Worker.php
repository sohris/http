<?php

namespace Sohris\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Sohris\Core\Loop;

use function React\Promise\Timer\resolve;

class Worker
{


    public function __construct($socket)
    {
        $loop = Loop::getLoop();
        $this->server = new \React\Http\Server($loop, function () {
            return new Response(200, [], "aaaa");
        });
        $this->socket = new \React\Socket\UnixServer($socket, $loop);
        $this->server->listen($this->socket);
    }
}
