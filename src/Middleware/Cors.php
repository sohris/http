<?php

namespace Sohris\Http\Middleware;

use function React\Promise\resolve;
use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\AnalysisResultInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Sikei\React\Http\Middleware\CorsMiddlewareAnalysisStrategy as Strategy;
use Sikei\React\Http\Middleware\CorsMiddlewareConfiguration as Config;
use Throwable;

class Cors
{
    private $analyzer;

    /**
     * @var Config
     */
    private $config;

    public function __construct($configs)
    {
        $this->config = new Config($configs);
        $this->analyzer = Analyzer::instance(new Strategy($this->config));
    }

    /**
     * @param DBSnoopRequest|ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke($request, $next)
    {
        $cors = $this->analyzer->analyze($request);
        switch ($cors->getRequestType()) {
            case AnalysisResultInterface::TYPE_PRE_FLIGHT_REQUEST:
                return new Response($this->config->getPreFlightResponseCode(), $cors->getResponseHeaders());
            case AnalysisResultInterface::ERR_NO_HOST_HEADER:
                return new Response(400, [], 'No host header present');
            case AnalysisResultInterface::ERR_HEADERS_NOT_SUPPORTED:
                return new Response(401, [], 'Headers not supported');
            case AnalysisResultInterface::ERR_ORIGIN_NOT_ALLOWED:
                return new Response(403, [], 'Origin not allowed');
            case AnalysisResultInterface::ERR_METHOD_NOT_SUPPORTED:
                return new Response(405, [], 'Method not supported');
        }
        return resolve($next($request))->then(function ($response) use ($cors) {
            try {
                foreach ($cors->getResponseHeaders() as $header => $value) {
                    $response = $response->withHeader($header, $value);
                }
                return $response;
            } catch (Throwable $e) {
                throw $e;
            }
        });
    }
}
