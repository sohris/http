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
    private  $mapper = [];

    public function getRoutesMapper()
    {
        $this->loadRoute($this);
        return $this->mapper;
    }

    private  function loadRoute($class)
    {
        if (empty($this->mapper)) {
            $class_annotation = Utils::loadAnnotationsOfClass($class);
            foreach ($class_annotation['methods'] as $method) {
                $this->mountMethodMap($method);
            }
        }
    }

    private  function mountMethodMap($method)
    {
        try {
            $this->validRouteMethod($method);
            $this->mappingRoute($method);
        } catch (Throwable $e) {
            //echo $e->getMessage();
        }
    }

    private  function validRouteMethod($method)
    {
        if (empty($method['annotation']))
            throw new Exception("Not Valid Method");
    }

    private  function mappingRoute($method)
    {
        if (empty(array_filter($method['annotation'], fn ($annotation) => $annotation instanceof \Sohris\Http\Annotations\Route)))
            throw new Exception("Not Valid Method");

        $route_hash = $this->getRouteHash($method);

        $this->mapper[$route_hash] = $this->configureMap($method);
    }

    private  function getRouteHash($method)
    {
        $annotation = array_filter($method['annotation'], fn ($annotation) => $annotation instanceof \Sohris\Http\Annotations\Route)[0];
        return $annotation->getHashRoute();
    }

    private  function configureMap($method)
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