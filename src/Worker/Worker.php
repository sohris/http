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


    public function __construct(string $port)
    {
        $this->uri = "127.0.0.1:$port";

        $this->install();

        $this->start();

        $this->event = new EventEmitter;
    }

    public function install()
    {

        $bootstrap = Server::getRootDir() . DIRECTORY_SEPARATOR . "bootstrap.php";

        $this->channel_name = "Channel_". $this->uri;

        $this->channel = Channel::make($this->channel_name, Channel::Infinite);

        $this->events = new Events;
        $this->events->addChannel($this->channel);
        $this->events->setBlocking(false);

        $this->runtime = new Runtime($bootstrap);
    }

    public function start()
    {
        $uri = $this->uri;
        $this->runtime->run(function ($channel) use ($uri) {
            $log = new Logger("Http");
            try {
                $log->debug("Starting Worker in $uri", [$uri]);
                $loop = Loop::get();
                RouterKernel::loadRoutes();
                $server = new \React\Http\HttpServer(...self::configuredMiddlewares($uri));
                $socket = new \React\Socket\SocketServer($uri);
                $server->listen($socket);
                $channel->send(["STATUS" => "LISTEN"]);
                $loop->run();
            } catch (Exception $e) {
                $log->critical("Error Worker [$uri]", [$e->getMessage()]);
            }
        }, [$this->channel]);
    }

    private function checkEvent()
    {

        $event = $this->events->poll();
        if ($event && $event->source == $this->channel_name) {
            $this->events->addChannel($this->channel);
            switch ($event->value['STATUS']) {
                case "LISTEN":
                    Loop::cancelTimer($this->timer);
                    $this->configureStream();
                    break;
            }
        }
    }

    private function configureStream()
    {
        $this->stream = new DuplexResourceStream($this->uri);
        $this->stream->on('data', fn($data) => $this->event->emit("data", $data));
    }

    private static function configuredMiddlewares(string $uri)
    {
        $middlewares = self::loadMiddlewares();
        $configs =  Utils::getConfigFiles('http');

        $array = [
            new MiddlewareLogger($uri),
            new Error,
            new Cors($configs['cors_config']),
            new LimitConcurrentRequestsMiddleware($configs['max_concurrent_requests']),
            new RequestBodyParserMiddleware($configs['upload_files_size'], $configs['max_upload_files']),
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

    public function on(string $event, callable $callable)
    {
        $this->event->on($event, $callable);
    }

    public function write(string $data)
    {
        $this->stream->write($data);
    }
}
