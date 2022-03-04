<?php

namespace Sohris\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use Sohris\Core\Component\AbstractComponent;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Utils;
use Sohris\Http\Handler;
use Sohris\Http\Router\Kernel as RouterKernel;
use Sohris\Http\Middleware\Error;
use Sohris\Http\Middleware\Cors;
use Sohris\Http\Middleware\Logger as MiddlewareLogger;
use Sohris\Http\Middleware\Router as MiddlewareRouter;

class Http extends AbstractComponent
{
    private $module_name = "Sohris_Http";

    private $socket;

    private $server;

    private $logger;

    private $routes_loaded;

    private $configs = array();

    public function __construct()
    {
        $this->configs = Utils::getConfigFiles('http');
        $this->host = $this->configs['host'] . ":" . $this->configs["port"];
        $this->loop = Loop::get();
        $this->logger = new Logger('Http');
    }

    public function install()
    {
        $this->loadMiddlewares();
        $this->logger->debug("Loaded Middlewares [" . sizeof($this->middlewares) . "]", $this->middlewares);

        RouterKernel::loadRoutes();
        $this->logger->debug("Loaded Routes [" . RouterKernel::getQuantityOfRoutes() . "]");
    }


    private function loadMiddlewares()
    {
        $this->middlewares = Loader::getClassesWithInterface("Sohris\Http\IMiddleware");
        usort($this->middlewares, fn ($a, $b) => $a::$priority < $b::$priority);
    }

    public function start()
    {
        $this->server = new \React\Http\HttpServer(...$this->configuredMiddlewares());
        $this->socket = new \React\Socket\SocketServer($this->host);
        $this->server->listen($this->socket);
    }

    public function getName(): string
    {
        return $this->module_name;
    }

    private function configuredMiddlewares()
    {

        $array = [
            new MiddlewareLogger,
            new Error,
            new Cors($this->configs['cors_config']),
            new LimitConcurrentRequestsMiddleware($this->configs['max_concurrent_requests']),
            new RequestBodyParserMiddleware($this->configs['upload_files_size'], $this->configs['max_upload_files']),
        ];
        foreach ($this->middlewares as $middleware) {
            array_push($array, new $middleware());
        }
        $array[] = new MiddlewareRouter;
        return $array;
    }
}
