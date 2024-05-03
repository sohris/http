<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Closure;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Sohris\Core\Logger as CLogger;
use Sohris\Core\Utils;
use Sohris\Http\Http;
use Sohris\Http\Response;

use function React\Promise\resolve;

class Logger
{
    private $logger;

    public function __construct()
    {
        $this->logger = new CLogger('CoreHttp');
    }

    public function __invoke(RequestInterface $request, Closure $next)
    {
        $start = Utils::microtimeFloat();
        Http::addRequest();
        return resolve($next($request))->then(function (ResponseInterface $response) use ($request, $start) {
            $end = Utils::microtimeFloat();
            Http::addTime($end - $start);
            $message = $request->getMethod() . " " . $response->getStatusCode() . " - " .  $request->getRequestTarget() . "  " . round(($end - $start), 3) . "sec ";
            $this->logger->debug($message);
            return $response;
        }, function (Exception $e) use ($start){
            $this->logger->exception($e);
            $end = Utils::microtimeFloat();
            Http::addTime($end - $start);
            return  Response::Json('INTERNAL_ERROR', 500);
        });
    }
}
