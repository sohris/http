<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Closure;
use React\Http\Message\Response;
use Sohris\Core\Logger;
use Sohris\Http\Exceptions\StatusHTTPException;
use Sohris\Http\IMiddleware;
use Throwable;

use function React\Promise\resolve;

class Error
{

    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('Http');
    }

    public function __invoke(ServerRequestInterface $request, Closure $next = null)
    {
       
        try {
            return resolve($next($request))->then(null, function (\Exception $e) {
                if (strpos(50, chr($e->getCode())))
                    $this->logger->critical($e->getMessage(), array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
                else
                    $this->logger->warning($e->getMessage(), array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
                return new Response(
                    $e->getCode(),
                    array(
                        'Content-Type' => 'application/json'
                    ),
                    json_encode(array("error" => $e->getCode(), "info" => $e->getMessage()))
                );
            });
        } catch (Throwable $e) {
            $code = $e->getCode();
            if($e->getCode() == 0)
            {
                $code = 500;
            }
            if (strpos(50, chr($code)))
                $this->logger->critical($e->getMessage(), array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
            else
                $this->logger->warning($e->getMessage(), array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
            return new Response(
                $code,
                array(
                    'Content-Type' => 'application/json'
                ),
                json_encode(array("error" => $code, "info" => $e->getMessage()))
            );
        }
    }
}
