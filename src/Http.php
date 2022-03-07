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

class Http extends AbstractComponent
{
    private $module_name = "Sohris_Http";

    private $socket;

    private $server;

    private $logger;

    private $workers = 1;

    private $configs = array();

    private $worker_control;

    public function __construct()
    {
        $this->configs = Utils::getConfigFiles('http');
        $this->host = $this->configs['host'] . ":" . $this->configs["port"];
        $this->workers = $this->configs['workers'] > 1 ? 1 :$this->configs['workers']; 
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
        $this->controller = new Controller($this->configs['workers']);
    }

    public function getName(): string
    {
        return $this->module_name;
    }

}
