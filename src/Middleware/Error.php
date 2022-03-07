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
            $promise = resolve($next($request));
            
            return $promise->then(null, function(\Exception $e){
            });
        } catch (StatusHTTPException $e) {
            return new Response(
                $e->getCode(),
                array(
                    'Content-Type' => 'application/json'
                ),
                json_encode(array("error" => $e->getCode(), "info" => $e->getMessage()))
            );
        } catch (Throwable $e) {
            $this->logger->critical($e->getMessage());
            return new Response(
                "500",
                array(
                    'Content-Type' => 'application/json'
                ),
                "INTERNAL ERROR"
            );
        }
    }
}
