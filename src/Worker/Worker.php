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

    private $limit;

    private $memory = 0;

    private $max_memory_limit = 70;

    private $channel_name = "";

    private $enable_nginx_controller = false;

    private $timer_restart;

    private $connections = 0;

    private $logger;


    public function __construct(string $uri)
    {
        $this->uri = $uri;
        $this->config = Utils::getConfigFiles('http');
        $this->enable_nginx_controller = $this->config['nginx_config_file'];
        $this->loop = Loop::get();
        $this->limit = $this->toInteger(ini_get("memory_limit"));
        $this->logger = new Logger('Http');

        $this->createChannel();

        $this->install();

        $this->start();

        $this->loop->addPeriodicTimer(1, fn () => $this->checkEvent());
        $this->event = new EventEmitter;
    }

    private function createChannel()
    {
        $this->channel_name = "worker_control_" . substr($this->uri, -2);

        $this->channel = Channel::make($this->channel_name, Channel::Infinite);

        $this->events = new Events;
        $this->events->addChannel($this->channel);
        $this->events->setBlocking(false);
    }

    public function toInteger($string)
    {
        sscanf($string, '%u%c', $number, $suffix);
        if (isset($suffix)) {
            $number = $number * pow(1024, strpos(' KMG', strtoupper($suffix)));
        }
        return $number;
    }

    public function install()
    {

        $this->createChannel();

        if($this->enable_nginx_controller){
            $uri = $this->uri;
            $file = $this->enable_nginx_controller;
            exec("sed -i 's/$uri down/$uri/g' $file && nginx -s reload");
        }
        $bootstrap = Server::getRootDir() . DIRECTORY_SEPARATOR . "bootstrap.php";

        $this->runtime = new Runtime($bootstrap);
    }

    public function start()
    {
        $uri = $this->uri;
        $this->runtime->run(function ($channel) use ($uri) {
            $log = new Logger("Http");
            try {
                $log->debug("Starting Worker in $uri", [$uri]);
                RouterKernel::loadRoutes();

                $server = new \React\Http\HttpServer(...self::configuredMiddlewares($uri));
                $socket = new \React\Socket\SocketServer($uri);
                $socket->on('connection', function ($connection) use ($channel) {
                    $channel->send(["STATUS" => "ADD_REQUEST"]);
                    $connection->on('close', fn () => $channel->send(["STATUS" => "SUB_REQUEST"]));
                });
                $server->listen($socket);

                Loop::addPeriodicTimer(1, fn () => $channel->send(["STATUS" => "MEMORY", "USAGE" => memory_get_peak_usage()]));

                Loop::run();
            } catch (Throwable $e) {
                $log->critical("Error Worker [$uri]", [$e->getMessage()]);
            }
        }, [$this->channel]);

        if ($this->enable_nginx_controller)
            $this->timer = $this->loop->addPeriodicTimer(15, fn () => $this->checkWorker());
    }

    public function checkWorker()
    {
        $perc = $this->memory * 100 / $this->limit;
        if ($perc >= $this->max_memory_limit) {
            $file = $this->enable_nginx_controller;
            $uri = $this->uri;
            exec("sed -i 's/$uri/$uri down/g' $file && nginx -s reload");
            $this->logger->debug("Try Restart", [$this->uri]);
            $this->timer_restart = $this->loop->addPeriodicTimer(1, fn () => $this->restart());
        }
    }

    private function restart()
    {
        if ($this->connections <= 0) {
            $this->logger->debug("Restarting ", [$this->uri]);
            $this->connections = 0;
            $this->memory = 0;
            $this->runtime->kill();
            $this->loop->cancelTimer($this->timer);
            $this->loop->cancelTimer($this->timer_restart);
            $this->install();
            $this->start();
        }

        if ($this->enable_nginx_controller) {
            $file = $this->enable_nginx_controller;
            $uri = $this->uri;
            exec("sed -i 's/$uri down/$uri/g' $file && nginx -s reload");
        }
    }

    private function checkEvent()
    {
        $event = $this->events->poll();

        if ($event && $event->source == $this->channel_name) {
            $this->events->addChannel($this->channel);
            switch ($event->value['STATUS']) {
                case "MEMORY":
                    $this->memory = $event->value["USAGE"];
                    break;
                case "ADD_REQUEST":
                    ++$this->connections;
                    break;
                case "SUB_REQUEST":
                    --$this->connections;
                    break;
            }
        }
    }

    private static function configuredMiddlewares(string $uri)
    {
        $middlewares = self::loadMiddlewares();
        $configs =  Utils::getConfigFiles('http');

        $array = [
            new \React\Http\Middleware\StreamingRequestMiddleware(),
            new \React\Http\Middleware\LimitConcurrentRequestsMiddleware($configs['max_concurrent_requests']),
            new \React\Http\Middleware\RequestBodyBufferMiddleware(2 * 1024 * 1024),
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
