<?php

namespace Sohris\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Sohris\Core\Logger;
use Sohris\Http\Http;

class Debug
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger("CoreHttp");
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke($request, $next)
    {
        $stats = Http::getStats();
        $this->logger->debug("Http Server Debug");
        $this->logger->debug("Request " . $request->getUri()->getPath());
        $this->logger->debug("Uptime " . $stats['uptime']);
        $this->logger->debug("Requests " . $stats['requests']);
        $this->logger->debug("Requests Per Sec " . $stats['requests_per_sec']);
        $this->logger->debug("Time Used " . $stats['time']);
        $this->logger->debug("Active Connections " . $stats['active_connections']);
        $this->logger->debug("Avg time Request " . $stats['avg_time_request']);
        return $next($request);
    }
}
