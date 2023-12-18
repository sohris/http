<?php

namespace Sohris\Http\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RingCentral\Psr7\Request;
use Sohris\Core\Exceptions\ServerException;
use Sohris\Core\Loader;
use Sohris\Http\Annotations\Needed;
use Sohris\Http\Annotations\SessionJWT;
use Sohris\Http\Exceptions\StatusHTTPException;
use Sohris\Http\Utils;

class Kernel
{

    private static $router_map = [];

    public static function loadRoutes()
    {   
        foreach (Loader::getClassesWithParent("Sohris\Http\Router\RouterControllers\DRMRouter") as $route_class) {
            $class = new $route_class;
            self::$router_map = array_merge($class->getRoutesMapper(), self::$router_map);
        }
    }

    public static function getClassOfRoute(string $route_hash)
    {
        self::isValidRoute($route_hash);
        return self::$router_map[$route_hash];
    }

    public static function isValidRoute(string $route_hash)
    {
        if (!key_exists($route_hash, self::$router_map)) {
            throw new StatusHTTPException("Page not found!", 404);
        }
    }

    public static function isValidMethod(string $path_route, string $method)
    {
        $class = self::getClassOfRoute($path_route);
        $class->method->valid($method);
    }

    public static function getQuantityOfRoutes()
    {
        return sizeof(self::$router_map);
    }

    public static function getSessionJWT(string $route_hash, ServerRequestInterface $request)
    {
        $class = self::getClassOfRoute($route_hash);
        if (isset($class->session_jwt) && $class->session_jwt->needAuthorization()) {
            return SessionJWT::getSession($request);
        }
        return null;
    }

    public static function validNeeded(ServerRequestInterface &$request)
    {
        $class = self::getClassOfRoute($request->route_hash);
        $request->REQUEST = [];
        if (isset($class->needed))
            $request->REQUEST = $class->needed->getNeededInRequest($request);
        $request->FILES = Needed::getFilesInRequest($request);
    }

    public static function callRoute(ServerRequestInterface $request)
    {
        $class = self::getClassOfRoute($request->route_hash);
        $handler = $class->callable;
        return $handler($request);
    }
}
