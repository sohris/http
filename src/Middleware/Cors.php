<?php

namespace Sohris\Http\Middleware;

use function React\Promise\resolve;

use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\AnalysisResultInterface;
use Neomerx\Cors\Strategies\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use React\Http\Message\Response;
use Sohris\Core\Logger;
use Sohris\Core\Utils;
use Throwable;

class Cors
{
    private $analyzer;

    private $config;

    public function __construct()
    {
        $system_config = Utils::getConfigFiles('http')['cors_config'];
        $host = Utils::getConfigFiles('system')['bhostname'];
        $exploded = explode("://", $host);
        $exploded2 = explode(":", $exploded[1]);
        $this->config = new Settings();
        $this->config
            ->setServerOrigin($exploded[0], $exploded2[0], isset($exploded2[1]) ? $exploded2[1] : ($exploded[0] == "https" ? "443" : "80"))
            ->enableAddAllowedHeadersToPreFlightResponse()
            ->enableAddAllowedMethodsToPreFlightResponse()
            ->enableAllOriginsAllowed()
            ->setAllowedMethods($system_config['allow_methods'])
            ->setAllowedHeaders($system_config['allow_headers'])
            ->setExposedHeaders($system_config['expose_headers'])
            ->disableCheckHost()
            ->setCredentialsSupported()
            ->setPreFlightCacheMaxAge($system_config['max_age'])
            ->setLogger(new Logger("HttpCors"));

        $this->analyzer = Analyzer::instance($this->config);
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke($request, $next)
    {
        $cors = $this->analyzer->analyze($request);
        switch ($cors->getRequestType()) {
            case AnalysisResultInterface::TYPE_PRE_FLIGHT_REQUEST:
                return new Response(200, $cors->getResponseHeaders());
            case AnalysisResultInterface::ERR_NO_HOST_HEADER:
                return new Response(400, [], 'No host header present');
            case AnalysisResultInterface::ERR_ORIGIN_NOT_ALLOWED:
                return new Response(403, [], 'Origin not allowed');
            case AnalysisResultInterface::ERR_METHOD_NOT_SUPPORTED:
                return new Response(405, [], 'Method not supported');
            case AnalysisResultInterface::ERR_HEADERS_NOT_SUPPORTED:
                return new Response(401, [], 'Headers not supported');
            case AnalysisResultInterface::TYPE_REQUEST_OUT_OF_CORS_SCOPE:
            default:
                // call next middleware handler
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
}
