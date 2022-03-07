<?php

namespace Sohris\Http;

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
use Sohris\Core\Utils;
use Sohris\Http\Handler;
use Sohris\Http\Router\Kernel as RouterKernel;
use Sohris\Http\Middleware\Error;
use Sohris\Http\Middleware\Cors;
use Sohris\Http\Middleware\Logger as MiddlewareLogger;
use Sohris\Http\Middleware\Router as MiddlewareRouter;
use Sohris\Http\Worker\Controller;
use Sohris\Http\Worker\Worker;

class Http extends AbstractComponent
{
    private $module_name = "Sohris_Http";

    private $socket;

    private $workers_queue;

    private $server;

    private $logger;

    private $loop;

    private $workers = 1;

    private $configs = array();

    private $worker_control;

    public function __construct()
    {
        $this->configs = Utils::getConfigFiles('http');
        $this->host = $this->configs['host'] . ":" . $this->configs["port"];
        $this->workers = $this->configs['workers'] < 1 ? 1 : $this->configs['workers'];
        $this->loop = Loop::get();
        $this->logger = new Logger('Http');
    }

    public function install()
    {
        $this->loadMiddlewares();
        $this->logger->debug("Loaded Middlewares [" . sizeof($this->middlewares) . "]", $this->middlewares);
        RouterKernel::loadRoutes();
        $this->logger->debug("Loaded Routes [" . RouterKernel::getQuantityOfRoutes() . "]");
        $this->loop->addPeriodicTimer(60, function () {});
    }


    private function loadMiddlewares()
    {
        $this->middlewares = Loader::getClassesWithInterface("Sohris\Http\IMiddleware");
        usort($this->middlewares, fn ($a, $b) => $a::$priority < $b::$priority);
    }

    public function start()
    {

        if ($this->workers == 1) {
            $this->workers_queue[] = new Worker($this->host);
            return;
        }

        for ((int) $i = 1; $i <= $this->workers; $i++) {
            $uri = $this->configs['host'] . ":" . (80 + $i);
            $this->workers_queue[] = new Worker($uri);
        }
    }

    public function getName(): string
    {
        return $this->module_name;
    }

    private function configuredMiddlewares(string $uri)
    {
        $configs =  Utils::getConfigFiles('http');

        $array = [
            new MiddlewareLogger($uri),
            new Error,
            new Cors($configs['cors_config']),
            new LimitConcurrentRequestsMiddleware($configs['max_concurrent_requests']),
            new RequestBodyParserMiddleware($configs['upload_files_size'], $configs['max_upload_files']),
        ];

        foreach ($this->middlewares as $middleware) {
            array_push($array, new $middleware());
        }
        $array[] = new MiddlewareRouter;
        return $array;
    }
}
