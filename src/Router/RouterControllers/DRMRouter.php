<?php


namespace Sohris\Http\Router\RouterControllers;

use Exception;
use RingCentral\Psr7\Request;
use RingCentral\Psr7\Response;
use Sohris\Http\Utils;
use stdClass;
use Throwable;

abstract class DRMRouter
{
    private static $mapper = [];

    public function getRoutesMapper()
    {
        self::loadRoute($this);

        return self::$mapper;
    }

    private static function loadRoute($class)
    {
        if (empty(self::$mapper)) {
            $class_annotation = Utils::loadAnnotationsOfClass($class);
            foreach ($class_annotation['methods'] as $method) {
                self::mountMethodMap($method);
            }
        }
    }

    private static function mountMethodMap($method)
    {
        try {
            self::validRouteMethod($method);
            self::mappingRoute($method);
        } catch (Throwable $e) {
        }
    }

    private static function validRouteMethod($method)
    {
        if (empty($method['annotation']))
            throw new Exception("Not Valid Method");
    }

    private static function mappingRoute($method)
    {
        if (empty(array_filter($method['annotation'], fn ($annotation) => $annotation instanceof \Sohris\Http\Annotations\Route)))
            throw new Exception("Not Valid Method");

        $route_hash = self::getRouteHash($method);

        self::$mapper[$route_hash] = self::configureMap($method);
    }

    private static function getRouteHash($method)
    {
        $annotation = array_filter($method['annotation'], fn ($annotation) => $annotation instanceof \Sohris\Http\Annotations\Route)[0];
        return $annotation->getHashRoute();
    }

    private static function configureMap($method)
    {
        $mapper = new stdClass;
        $mapper->callable = $method['method']->class . "::" . $method['method']->name;

        foreach ($method['annotation'] as $annotation) {
            if ($annotation instanceof \Sohris\Http\Annotations\Route) {
                $mapper->route = $annotation;
            } else if ($annotation instanceof \Sohris\Http\Annotations\HttpMethod) {
                $mapper->method = $annotation;
            } else if ($annotation instanceof \Sohris\Http\Annotations\Needed) {
                $mapper->needed = $annotation;
            } else if ($annotation instanceof \Sohris\Http\Annotations\SessionJWT) {
                $mapper->session_jwt = $annotation;
            }
        }

        return $mapper;
    }
}
