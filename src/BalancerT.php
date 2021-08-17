<?php
namespace Sohris\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

class BalancerT
{
    private static $sockets_workers = array();

    public function __construct(int $workers)
    {
        while($workers--)
        {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_UDP);

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

    private static function getNextWorker()
    {
        // $worker = \current(self::$workers_queue);
        // if(!$worker)
        // {
        //     \reset(self::$workers_queue);
        //     $worker = \current(self::$workers_queue);
        // }

        // \next(self::$workers_queue);
        // return $worker;
    }

}