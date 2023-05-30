<?php

namespace Sohris\Http\Worker;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use React\EventLoop\Loop;
use React\Http\Middleware\RequestBodyParserMiddleware;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Tools\Worker\ChannelController;
use Sohris\Core\Tools\Worker\Worker as CoreWorker;
use Sohris\Core\Utils;
use Sohris\Http\Router\Kernel as RouterKernel;
use Sohris\Http\Middleware\Error;
use Sohris\Http\Middleware\Cors;
use Sohris\Http\Middleware\Logger as MiddlewareLogger;
use Sohris\Http\Middleware\Router as MiddlewareRouter;
use Throwable;

class Worker
{

    private $uri;
    private $connections = 0;
    private $timer = 0;
    private $requests = 0;
    private $process_requests = 0;
    private $uptime;
    private $memory = 0;
    private $server;
    private $logger;
    private $client;
    private static $mysql;
    private $database;


    /**
     * @var CoreWorker
     */
    private $worker;
    private static $configs;


    public function __construct(string $url, $port = 80)
    {
        $uri = "$url:$port";
        self::firstRun();
        $this->server = Server::getServer();
        $this->uri = $uri;
        $this->logger = new Logger('Http');
        $this->client = new Client([
            "base_uri" => "http://" . ($url == '0.0.0.0' ? "localhost:$port" : $uri)
        ]);
        $this->worker = new CoreWorker;
        $this->worker->stayAlive();
        $this->worker->on('memory_usage', fn ($el) => $this->memory = $el);
        $this->worker->on('add_connection', fn () => $this->connections++);
        $this->worker->on('remove_connection', fn () => $this->connections--);
        $this->worker->on('add_request', fn () => $this->requests++);
        $this->worker->on('add_process_request', fn () => $this->process_requests++);
        $this->worker->on('add_timer', fn ($el) => $this->timer += $el);
        $this->worker->on('set_uptime', fn ($el) => $this->uptime = $el);
        self::$mysql = $this->server->getComponent("Sohris\Mysql\Mysql");
        $this->start();
        Loop::addPeriodicTimer(60, fn () => $this->checkIsUp());
    }

    private static function firstRun()
    {
        if (!self::$configs) {
            self::$configs = Utils::getConfigFiles('http');
        }
    }

    public static function toInteger($string)
    {
        sscanf($string, '%u%c', $number, $suffix);
        if (isset($suffix)) {
            $number = $number * pow(1024, strpos(' KMG', strtoupper($suffix)));
        }
        return $number;
    }

    public function start()
    {
        $uri = $this->uri;
        $channel_name = $this->worker->getChannelName();
        $this->worker->callOnFirst(static function () use ($uri, $channel_name) {
            try {
                ChannelController::send($channel_name, 'set_uptime', time());
                $log = new Logger('Http');
                $log->debug("Starting Worker in $uri", [$uri]);
                RouterKernel::loadRoutes();
                $server = new \React\Http\HttpServer(...self::configuredMiddlewares($uri, $channel_name));
                $socket = new \React\Socket\SocketServer($uri);
                $socket->on('connection', function ($connection) use ($channel_name) {
                    ChannelController::send($channel_name, 'add_connection');
                    $connection->on('close', fn () => ChannelController::send($channel_name, 'remove_connection'));
                });
                $server->listen($socket);

                $socket->on('error', function (Exception $e) use ($channel_name) {
                    ChannelController::send($channel_name, 'socket_error', [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage()
                    ]);
                });
                $server->on('error', function (Exception $e) use ($channel_name) {
                    ChannelController::send($channel_name, 'server_error', [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage()
                    ]);
                });
                Loop::addPeriodicTimer(10, fn () =>
                ChannelController::send($channel_name, 'memory_usage', memory_get_peak_usage()));                
            } catch (Exception $e) {
                $log = new Logger("Http");
                $log->critical("Error Worker [$uri]", [$e->getMessage()]);
            }
        });
        if (self::$mysql) {
            $this->worker->callFunction(static function ($emitter) {
                if (!self::$mysql) {
                    $server = Server::getServer();
                    self::$mysql = $server->getComponent("Sohris\Mysql\Mysql");
                }
                $emitter('database_update', self::$mysql->getStats());
            },10);
            $this->worker->on('database_update', function ($info){
                $this->database = $info;
            });
        }
        $this->worker->run();
    }

    private function checkIsUp()
    {
        try {
            $this->client->get("/", ['timeout' => 5]);
        } catch (GuzzleException $e) {
            if (empty($e->getCode())) {
                $this->restart();
            }
        }
    }

    public function restart()
    {
        $this->worker->restart();
    }

    private static function configuredMiddlewares(string $uri, string $channel_key)
    {
        $middlewares = self::loadMiddlewares();
        $configs =  Utils::getConfigFiles('http');

        $array = [
            new \React\Http\Middleware\StreamingRequestMiddleware(),
            new \React\Http\Middleware\LimitConcurrentRequestsMiddleware($configs['max_concurrent_requests']),
            new \React\Http\Middleware\RequestBodyBufferMiddleware(2 * 1024 * 1024),
            new \React\Http\Middleware\RequestBodyParserMiddleware(),
            new RequestBodyParserMiddleware($configs['upload_files_size'], $configs['max_upload_files']),
            new MiddlewareLogger($uri, $channel_key),
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

    public function getStats()
    {

        $uptime = time() - $this->uptime;
        $stats = [
            'url' => $this->uri,
            'memory_usage' => $this->memory,
            'uptime' => $uptime,
            'requests' => $this->requests,
            'request_per_sec' => round($this->requests / $uptime, 3),
            'process_requests' => $this->process_requests,
            'active_requests' => $this->requests - $this->process_requests,
            'active_connections' => $this->connections,
            'time_process_requests' => round($this->timer, 3),
            'avg_time_request' =>  $this->requests <= 0 ? 0 : round($this->timer / $this->requests, 3)
        ];

        if($this->database)
            $stats['database'] = $this->database;


        return $stats;
    }
}
