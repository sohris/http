<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sohris\Http\Router\Kernel;
use Sohris\Http\Router\Message\Request;
use Sohris\Http\Utils;

use function React\Promise\resolve;

class Router
{
    public function __invoke(RequestInterface $request)
    {
        $request = Request::fromRequest($request);
        $route_hash = Utils::hashOfRoute($request->getUri()->getPath());
        Kernel::isValidRoute($route_hash);
        Kernel::isValidMethod($route_hash, $request->getMethod());
        Kernel::validNeeded($request, $route_hash);

        $request->SESSION = Kernel::getSessionJWT($route_hash, $request);
        return resolve(Kernel::callRoute($request, $route_hash));
    }
}
