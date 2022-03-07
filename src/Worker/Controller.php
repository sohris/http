<?php

namespace Sohris\Http\Worker;

use React\Socket\ConnectionInterface;
use React\Stream\DuplexResourceStream;
use Sohris\Http\Utils;

class Controller
{
    private static $workers_queue = [];

    /**
     * @var DuplexResourceStream
     */
    private $stream;

    public function __construct(string $worker_pool_size)
    {
        $this->genetatePool($worker_pool_size);
        
    }

    public function genetatePool(int $worker_pool_size = 1)
    {
        for((int) $i = 0 ; $i < $worker_pool_size; $i++)
        {
            $port = Utils::getEnablePort();
            $worker = self::workerFactory(80 + $i);
            //$worker->on('data', fn($data) => $this->writeInStream($data));
            self::$workers_queue[] = $worker;
        }
    }

    private function writeInStream($data)
    {
        $this->stream->write($data);
    }

    private static function workerFactory(string $port)
    {
        return new Worker($port);
    }
    
    public function writeInPool(string $data)
    {
        $worker = self::getNextWorker();
        $worker->write($data);
    }

    private static function getNextWorker()
    {
        $worker = \current(self::$workers_queue);
        if(!$worker)
        {
            \reset(self::$workers_queue);
            $worker = \current(self::$workers_queue);
        }

        \next(self::$workers_queue);
        return $worker;
    }

}