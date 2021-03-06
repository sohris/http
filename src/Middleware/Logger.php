<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Closure;
use Exception;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Promise;
use Sohris\Core\Logger as CLogger;
use Sohris\Core\Utils;
use Sohris\Http\IMiddleware;

use function React\Promise\resolve;

class Logger
{
    private $logger;
    private $worker = '';

    public function __construct(string $uri)
    {
        $this->worker = $uri;
        $this->logger = new CLogger('Http');
        $this->debug = Utils::getConfigFiles('system')['debug'];
    }

    public function __invoke(ServerRequestInterface $request, Closure $next)
    {

        $start = self::microtime_float();

        return resolve($next($request))->then(function (ResponseInterface $response) use ($request, $start) {

            $end = self::microtime_float();
            $message = "[Status " . $response->getStatusCode() . "]  " . $request->getMethod() . " " .  $request->getRequestTarget() . "  " . round(($end - $start), 3) . "sec ";
            $this->logger->debug($message, [$this->worker]);
            if($this->debug)
                echo $message . PHP_EOL;
    
            return $response;
        });
    }

    public static function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}
