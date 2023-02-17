<?php

namespace Sohris\Http;

use Exception;
use React\EventLoop\Loop;
use Sohris\Core\ComponentControl;
use Sohris\Core\Logger;
use Sohris\Core\Utils;
use Sohris\Http\Router\Kernel as RouterKernel;
use Sohris\Http\Worker\Worker;

class Http extends ComponentControl
{
    private $module_name = "Sohris_Http";

    private $workers_queue;

    private $logger;

    private $workers = 1;

    private $configs = array();

    private $uptime;

    public function __construct()
    {
        $this->uptime = time();
        $this->configs = Utils::getConfigFiles('http');
        $this->workers = $this->configs['workers'] < 1 ? 1 : $this->configs['workers'];
        $this->logger = new Logger('Http');
    }

    public function install()
    {
        RouterKernel::loadRoutes();
        $this->logger->debug("Loaded Routes [" . RouterKernel::getQuantityOfRoutes() . "]");
       
    }

    public function start()
    {
        if ($this->workers == 1) {
            $key = sha1($this->configs['host'] . ":" . $this->configs['port']);
            $this->workers_queue[$key] = new Worker($this->configs['host'], $this->configs['port']);
            return;
        }
        for ((int) $i = 1; $i <= $this->workers; $i++) {
            $port = (80 + $i);
            $key = sha1($this->configs['host'] . ":$port");
            $this->workers_queue[$key] = new Worker($this->configs['host'], $port);
        }
    }

    public function getName(): string
    {
        return $this->module_name;
    }

    public function getStats()
    {
        
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
            $stats["workers"][] = $stat;
            $stats["total_requests"] += $stat['requests'];
            $stats["total_time"] += $stat['time_process_requests'];
            $stats["total_active_connections"] += $stat['active_connections'];
            $stats["total_active_requests"] += $stat['active_requests'];
            $stats['total_requests_per_sec'] += $stat['request_per_sec'];
        }
        $stats['total_requests_per_sec'] = round($stats['total_requests_per_sec']/count($this->workers_queue), 3);
        $stats['avg_time_request'] = $stats['total_requests'] <= 0? 0: round($stats['total_time'] / $stats['total_requests'], 3);

        return $stats;
    }

    public function getWorker($worker_host):Worker
    {      
        $key = sha1($worker_host);
        if(!array_key_exists($key, $this->workers)) throw new Exception("INVALID_WORKER");
        return $this->workers[$key];
    }
    
    public function hasWorker($worker_host):bool
    {      
        $key = sha1($worker_host);
        return array_key_exists($key, $this->workers);
    }
}
