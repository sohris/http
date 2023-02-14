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

    private $uptime;

    private $statistics_file = '';

    private $worker_control;

    private $host;

    public function __construct()
    {
        $this->uptime = time();
        $this->configs = Utils::getConfigFiles('http');
        $this->statistics_file = $this->configs['statistics_file'];
        $this->host = $this->configs['host'] . ":" . $this->configs["port"];
        $this->workers = $this->configs['workers'] < 1 ? 1 : $this->configs['workers'];
        $this->loop = Loop::get();
        $this->logger = new Logger('Http');
    }

    public function install()
    {
        RouterKernel::loadRoutes();
        $this->logger->debug("Loaded Routes [" . RouterKernel::getQuantityOfRoutes() . "]");
        $this->loop->addPeriodicTimer(60, function () {
            $stats = [
                'uptime' => time() - $this->uptime,
                'total_requests' => 0,
                'total_requests_per_sec' => 0,
                'total_time' => 0,
                'total_active_connections' => 0,
                'total_active_requests' => 0,
                'avg_time_request' => 0,
                'workers' => []
            ];
            foreach ($this->workers_queue as $key => $worker) {
                $stat = $worker->getStats();
                $stats["workers"][$key] = $stat;
                $stats["total_requests"] += $stat['requests'];
                $stats["total_time"] += $stat['time_process_requests'];
                $stats["total_active_connections"] += $stat['active_connections'];
                $stats["total_active_requests"] += $stat['active_requests'];
                $stats['total_requests_per_sec'] += $stat['request_per_sec'];
            }
            $stats['total_requests_per_sec'] = round($stats['total_requests_per_sec']/count($this->workers_queue), 3);
            $stats['avg_time_request'] = round($stats['total_time'] / $stats['total_requests'], 3);

            file_put_contents($this->statistics_file, json_encode($stats));
        });
    }

    public function start()
    {
        if ($this->workers == 1) {
            $this->workers_queue[] = new Worker($this->configs['host'], $this->configs['port']);
            return;
        }
        for ((int) $i = 1; $i <= $this->workers; $i++) {
            $this->workers_queue[] = new Worker($this->configs['host'], (80 + $i));
        }
    }

    public function getName(): string
    {
        return $this->module_name;
    }
}
