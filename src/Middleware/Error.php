<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Closure;
use Exception;
use React\Http\Message\Response;
use React\Promise\Promise;
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
            return $next($request)->then(null, function (Exception $e) use ($request) {
                self::log($request, $e);
                return self::getResponse($e);
            });
        } catch (StatusHTTPException $e) {
            self::log($request, $e);
            return self::getResponse($e);
        } catch (Throwable $e) {
            self::log($request, $e);
            return self::getResponse($e);
        }
    }

    private static function log($request, $e)
    {
        $logger = new Logger('CoreHttp');
        $message = $request->getMethod() . " " . $e->getCode() . " " .  $request->getRequestTarget() . " - " . $e->getMessage() . " (File: " . $e->getFile() . " Line: " . $e->getLine() . ")";
        if (strpos(50, chr($e->getCode())))
            $logger->critical($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
        else
            $logger->info($message, array_map(fn ($trace) => "File : $trace[file] (Line $trace[line])", array_slice($e->getTrace(), 0, 3)));
    }

    private static function getResponse(\Throwable $e)
    {
        $code = 500;
        if ($e instanceof \Sohris\Http\Exceptions\StatusHTTPException)
            $code =  $e->getCode();

        return resolve(new Response(
            $code,
            array(
                'Content-Type' => 'application/json'
            ),
            json_encode(array("status" => "error", "code" => $e->getCode(), "message" => $e->getMessage()))
        ));
    }
}
