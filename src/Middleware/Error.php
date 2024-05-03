<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Closure;
use Exception;
use React\Http\Message\Response;
use Sohris\Core\Logger;
use Sohris\Http\Exceptions\StatusHTTPException;
use Throwable;

use function React\Promise\resolve;

class Error
{

    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('CoreHttp');
    }

    public function __invoke(RequestInterface $request, Closure $next = null)
    {

        try {
            return resolve($next($request))->then(null, function (Exception $e) use ($request) {
                $message = $request->getMethod() . " " . $e->getCode() . " " .  $request->getRequestTarget() . " - " . $e->getMessage() . " (File: " . $e->getFile() . " Line: " . $e->getLine() . ")";
                if (strpos(50, chr($e->getCode())))
                    $this->logger->critical($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
                else
                    $this->logger->info($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
                return new Response(
                    500,
                    array(
                        'Content-Type' => 'application/json'
                    ),
                    json_encode(array("status" => "error", "code" => $e->getCode(), "message" => $e->getMessage()))
                );
            });
        } catch (StatusHTTPException $e) {
            $message = $request->getMethod() . " " . $e->getCode() . " " .  $request->getRequestTarget() . " - " . $e->getMessage() . " (File: " . $e->getFile() . " Line: " . $e->getLine() . ")";
            if (strpos(50, chr($e->getCode())))
                $this->logger->critical($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
            else
                $this->logger->info($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
            return new Response(
                $e->getCode(),
                array(
                    'Content-Type' => 'application/json'
                ),
                json_encode(array("status" => "error", "code" => $e->getCode(), "message" => $e->getMessage()))
            );
        } catch (Throwable $e) {
            $message = $request->getMethod() . " " . $e->getCode() . " " .  $request->getRequestTarget() . " - " . $e->getMessage() . " (File: " . $e->getFile() . " Line: " . $e->getLine() . ")";
            if (strpos(50, chr($e->getCode())))
                $this->logger->critical($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
            else
                $this->logger->info($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
            return new Response(
                500,
                array(
                    'Content-Type' => 'application/json'
                ),
                json_encode(array("status" => "error", "code" => $e->getCode(), "message" => $e->getMessage()))
            );
        }
    }
}
