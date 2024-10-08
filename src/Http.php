<?php

namespace Sohris\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\SocketServer;
use Sohris\Core\ComponentControl;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Tools\Worker\Worker;
use Sohris\Core\Utils;
use Sohris\Http\Middleware\Cors;
use Sohris\Http\Middleware\Debug;
use Sohris\Http\Middleware\Error;
use Sohris\Http\Middleware\Logger as MiddlewareLogger;
use Sohris\Http\Middleware\Router;
use Sohris\Http\Router\Kernel as RouterKernel;

class Http extends ComponentControl
{
    private $module_name = "Sohris_Http";

    private static Logger $logger;
    private Worker $worker;
    private Client $client;

    private static SocketServer $httpsocket;
    private static HttpServer $httpserver;

    public static int $current_connections = 0;

    private $configs = array();

    private static $uptime;

    private static $stats = [
        'requests' => 0,
        'connections' => 0,
        'time' => 0,
    ];

    public function __construct()
    {
        self::$uptime = time();
        $this->configs = Utils::getConfigFiles('http');
        self::$logger = new Logger('CoreHttp');
    }

    public function install()
    {
        RouterKernel::loadRoutes();
        self::$logger->info("Routes [" . RouterKernel::getQuantityOfRoutes() . "]");
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

        $this->client = new Client([
            "base_uri" => "http://" . $uri
        ]);
        self::$logger->debug("Creating Server");
        self::$logger = new Logger("CoreHttp");
        RouterKernel::loadRoutes();
        self::$httpserver = new HttpServer(...self::configuredMiddlewares($uri));
        self::$httpsocket = new SocketServer($uri);
        self::$httpsocket->on('connection', function ($connection) {
            $address = $connection->getRemoteAddress();
            $ip = trim(parse_url($address, PHP_URL_HOST), '[]');
            $address2 = $connection->getLocalAddress();
            self::$logger->debug("New Connection $ip <-> $address2");
            self::$stats['connections']++;
            $connection->on('close', function () {
                self::$stats['connections']--;
            });
            $connection->on('error', function (Exception $e) {
                self::$logger->exception($e);
            });
        });
        self::$httpserver->listen(self::$httpsocket);
        self::$httpsocket->on('error', function (Exception $e) {
            self::$logger->exception($e);
        });
        self::$httpserver->on('error', function (Exception $e) {
            self::$logger->exception($e);
        });

        self::$logger->debug("Server Http Created!");
        self::$logger->info("Listen in $uri");

    }

    public function restart()
    {
        $this->worker->restart();
    }

    private static function loadMiddlewares()
    {
        $middlewares = Loader::getClassesWithInterface("Sohris\Http\IMiddleware");
        usort($middlewares, fn ($a, $b) => $a::$priority < $b::$priority ? 1 : 0);
        return $middlewares;
    }

    private static function configuredMiddlewares(string $uri)
    {
        $middlewares = self::loadMiddlewares();
        $configs =  Utils::getConfigFiles('http');
        $array = [
            new Debug,
            new Cors($configs['cors_config']),
            new \React\Http\Middleware\StreamingRequestMiddleware(),
            new \React\Http\Middleware\LimitConcurrentRequestsMiddleware($configs['max_concurrent_requests']),
            new \React\Http\Middleware\RequestBodyBufferMiddleware(2 * 1024 * 1024),
            new \React\Http\Middleware\RequestBodyParserMiddleware(),
            new RequestBodyParserMiddleware($configs['upload_files_size'], $configs['max_upload_files']),
            new MiddlewareLogger($uri),
            new Error,
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

    public static function getStats()
    {
        $uptime = time() - self::$uptime;
        $stats = [
            'uptime' => $uptime,
            'requests' => self::$stats['requests'],
            'requests_per_sec' => round(self::$stats['requests'] / $uptime, 3),
            'time' => self::$stats['time'],
            'active_connections' => self::$stats['connections'],
            'avg_time_request' => self::$stats['requests'] > 0 ? round(self::$stats['time'] / self::$stats['requests'], 3) : 0
        ];
        return $stats;
    }

    public static function addTime(float $time)
    {
        self::$stats['time'] += $time;
    }

    public static function addRequest()
    {
        self::$stats['requests']++;
    }
}
