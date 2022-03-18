<?php

namespace Sohris\Http\Worker;

use Evenement\EventEmitter;
use Exception;
use parallel\Channel;
use parallel\Events;
use parallel\Runtime;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use Sohris\Core\Component\AbstractComponent;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use React\Stream\DuplexResourceStream;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Utils;
use Sohris\Http\Handler;
use Sohris\Http\Router\Kernel as RouterKernel;
use Sohris\Http\Middleware\Error;
use Sohris\Http\Middleware\Cors;
use Sohris\Http\Middleware\Logger as MiddlewareLogger;
use Sohris\Http\Middleware\Router as MiddlewareRouter;
use Throwable;

class Worker
{

    private $uri;

    private $events;

    private $runtime;

    private $channel;

    private $connection;

    private $socket;

    private $channel_name = "";

    private $event;


    public function __construct(string $uri)
    {
        $this->uri = $uri;

        $this->install();

        $this->start();

        $this->event = new EventEmitter;
    }

    public function install()
    {

        $bootstrap = Server::getRootDir() . DIRECTORY_SEPARATOR . "bootstrap.php";

        $this->runtime = new Runtime($bootstrap);
    }

    public function start()
    {
        $uri = $this->uri;
        $this->runtime->run(function () use ($uri) {
            $log = new Logger("Http");
            try {
                $log->debug("Starting Worker in $uri", [$uri]);
                RouterKernel::loadRoutes();

                $server = new \React\Http\HttpServer(...self::configuredMiddlewares($uri));
                $socket = new \React\Socket\SocketServer($uri);
                $socket->on('error', function (Exception $e) use ($log, $uri) {
                    echo $e->getMessage() . PHP_EOL;
                    $log->critical("Error Worker [$uri]", [$e->getMessage()]);
                });
                $server->listen($socket);
                Loop::run();
            } catch (Throwable $e) {
                $log->critical("Error Worker [$uri]", [$e->getMessage()]);
            }
        });
    }

    private static function configuredMiddlewares(string $uri)
    {
        $middlewares = self::loadMiddlewares();
        $configs =  Utils::getConfigFiles('http');

        $array = [
            new \React\Http\Middleware\StreamingRequestMiddleware(),
            new \React\Http\Middleware\LimitConcurrentRequestsMiddleware($configs['max_concurrent_requests']), // 100 concurrent buffering handlers
            new \React\Http\Middleware\RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2 MiB per request
            new \React\Http\Middleware\RequestBodyParserMiddleware(),
            new RequestBodyParserMiddleware($configs['upload_files_size'], $configs['max_upload_files']),
            new MiddlewareLogger($uri),
            new Error,
            new Cors($configs['cors_config']),
        ];

        foreach ($middlewares as $middleware) {
            array_push($array, new $middleware());
        }
        $array[] = new MiddlewareRouter;
        return $array;
    }

    private static function loadMiddlewares()
    {
        $middlewares = Loader::getClassesWithInterface("Sohris\Http\IMiddleware");
        usort($middlewares, fn ($a, $b) => $a::$priority < $b::$priority);

        return $middlewares;
    }
}
