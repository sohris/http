<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Closure;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Sohris\Core\Logger as CLogger;
use Sohris\Core\Tools\Worker\ChannelController;
use Sohris\Core\Utils;
use Sohris\Http\Response;

use function React\Promise\resolve;

class Logger
{
    private $logger;
    private $worker = '';
    private $debug = false;
    private $channel_key = '';
    private static $top_request = [];

    public function __construct(string $uri, string $channel_key)
    {
        $this->worker = $uri;
        $this->channel_key = $channel_key;
        $this->logger = new CLogger('Http');
        $this->debug = Utils::getConfigFiles('system')['debug'];
    }

    public function __invoke(ServerRequestInterface $request, Closure $next)
    {

        $start = self::microtime_float();
        ChannelController::send($this->channel_key,'add_request');

        return resolve($next($request))->then(function (ResponseInterface $response) use ($request, $start) {
            ChannelController::send($this->channel_key,'add_process_request');

            $end = self::microtime_float();
            $message = "[Status " . $response->getStatusCode() . "]  " . $request->getMethod() . " " .  $request->getRequestTarget() . "  " . round(($end - $start), 3) . "sec ";
            $this->logger->debug($message, [$this->worker]);
            if($this->debug)
                echo $message . PHP_EOL;

            ChannelController::send($this->channel_key,'add_timer', $end - $start);
            return $response;
        }, function (Exception $e) use ($start){
            $end = self::microtime_float();
            ChannelController::send($this->channel_key,'add_process_request');
            ChannelController::send($this->channel_key,'add_timer', $end - $start);
            $this->logger->critical($e->getMessage(), [$this->worker]);
            return  Response::Json('INTERNAL_ERROR', 500);
        });
    }

    public static function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

}
