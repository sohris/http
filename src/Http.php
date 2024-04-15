<?php

namespace Sohris\Http;

use Exception;
use React\Http\Middleware\RequestBodyParserMiddleware;
use Sohris\Core\ComponentControl;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Utils;
use Sohris\Http\Middleware\Cors;
use Sohris\Http\Middleware\Error;
use Sohris\Http\Middleware\Logger as MiddlewareLogger;
use Sohris\Http\Middleware\Router;
use Sohris\Http\Router\Kernel as RouterKernel;

class Http extends ComponentControl
{
    private $module_name = "Sohris_Http";

    private $logger;

    private $configs = array();

    private $uptime;

    private static $stats = [
        'requests' => 0,
        'connections' => 0,
        'time' => 0,
    ];

    public function __construct()
    {
        $this->uptime = time();
        $this->configs = Utils::getConfigFiles('http');
        $this->logger = new Logger('CoreHttp');
    }

    public function install()
    {
        RouterKernel::loadRoutes();
        $this->logger->info("Routes [" . RouterKernel::getQuantityOfRoutes() . "]");
    }

    public function start()
    {
        $this->configureServer();
    }

    public function configureServer()
    {

        $port = $this->configs['port'];
        $url = $this->configs['host'];
        $uri = ($url == 'localhost' ? "0.0.0.0:$port" : "$url:$port");

        $this->logger->debug("Creating Server");
        $server = new \React\Http\HttpServer(...$this->configuredMiddlewares($uri));
        $socket = new \React\Socket\SocketServer($uri);
        $socket->on('connection', function ($connection) {
            $this->logger->debug("New Connection");
            self::$stats['connections']++;
            $connection->on('close', function () {
                self::$stats['connections']--;
            });
        });

        $server->listen($socket);

        $socket->on('error', function (Exception $e) {
            $this->logger->exception($e);
        });

        $server->on('error', function (Exception $e) {
            $this->logger->exception($e);
        });
        
        $this->logger->debug("Server Http Created!");
        $this->logger->info("Listen in $uri");
    }


    private function loadMiddlewares()
    {
        $middlewares = Loader::getClassesWithInterface("Sohris\Http\IMiddleware");
        usort($middlewares, fn ($a, $b) => $a::$priority < $b::$priority);
        return $middlewares;
    }

    private function configuredMiddlewares(string $uri)
    {
        $middlewares = $this->loadMiddlewares();
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
        $array[] = new Router;
        return $array;
    }

    public function getName(): string
    {
        return $this->module_name;
    }

    public function getStats()
    {
        $uptime = time() - $this->uptime;
        $stats = [
            'uptime' => $uptime,
            'requests' => self::$stats['requests'],
            'requests_per_sec' => round(self::$stats['requests'] / $uptime, 3),
            'time' => self::$stats['time'],
            'active_connections' => self::$stats['connections'],
            'avg_time_request' => self::$stats['request'] > 0 ? round(self::$stats['time'] / self::$stats['request'], 3) : 0
        ];
        return $stats;
    }

    public static function addTime(float $time)
    {
        self::$stats['time']+=$time;
    }

    public static function addRequest()
    {
        self::$stats['requests']++;
    }
}
