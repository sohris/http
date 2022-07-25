<?php

namespace Sohris\Http\Middleware;

use Closure;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Sohris\Core\Exceptions\ServerException;
use Sohris\Http\Exceptions\StatusHTTPException;
use Sohris\Http\Request;
use Sohris\Http\Router as HttpRouter;
use Sohris\Http\Router\Kernel;
use Sohris\Http\Utils;

use function React\Promise\resolve;

class Router
{
    public function __invoke(ServerRequestInterface $request)
    {
        $request->route_hash = Utils::hashOfRoute($request->getUri()->getPath());
        Kernel::isValidRoute($request->route_hash);
        Kernel::isValidMethod($request->route_hash, $request->getMethod());
        Kernel::validNeeded($request);

        $request->SESSION = Kernel::getSessionJWT($request->route_hash, $request);
        return resolve(Kernel::callRoute($request));
    }   
}
