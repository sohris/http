<?php
namespace Sohris\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

class Balancer
{
    private static $workers_queue = array();

    public function __construct(array $workers)
    {
        if(empty(self::$workers_queue))
        {
            self::$workers_queue = $workers;
        }
    } 

    public function __invoke(ServerRequestInterface $request)
    {
        $next_worker = self::getNextWorker();

        return new Promise(function ($resolve) use ($request , $next_worker) {
            $response = $next_worker($request);
            return $resolve($response);
        });


    }

    private static function getNextWorker(): Worker
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