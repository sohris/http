<?php

namespace Sohris\Http;

use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use ReflectionClass;
use Sohris\Http\Routes\Rest;

class Utils
{
    const ALL_REST_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public static function loadAnnotationsOfClass($class)
    {
        $reader = new AnnotationReader();

        $reflection = new ReflectionClass($class);
        $configure = [
            "class" => $reflection,
            "annotations" => $reader->getClassAnnotations($reflection),
            "methods" => self::loadAnnotationsOfClassMethods($reflection)
        ];
        return $configure;
    }

    public static function loadAnnotationsOfClassMethods(ReflectionClass $class)
    {
        $reader = new AnnotationReader();

        $methods = $class->getMethods();
        $methods_configured = [];

        foreach ($methods as $method) {
            array_push($methods_configured, ["method" => $method, "annotation" => $reader->getMethodAnnotations($method)]);
        }
        return $methods_configured;
    }

    public static function utf8_encode_rec($value)
    {

        $newarray = array();

        if (is_array($value)) {
            foreach ($value as $key => $data) {
                $newarray[self::utf8_validate($key)] = self::utf8_encode_rec($data);
            }
        } else {
            return self::utf8_validate($value);
        }

        return $newarray;
    }


    public static function utf8_validate($string, $reverse = 0)
    {
        if ($reverse == 0) {

            if (preg_match('!!u', $string)) {
                return $string;
            } else {
                return utf8_encode($string);
            }
        }

        // Decoding
        if ($reverse == 1) {

            if (preg_match('!!u', $string)) {
                return utf8_decode($string);
            } else {
                return $string;
            }
        }

        return false;
    }

    public static function hashOfRoute(string $route)
    {
        preg_match_all('/\/?(\w*)/', $route, $output_array);
        $output_array = array_filter($output_array[1], fn ($el) => !empty($el));
        return sha1(implode('/', $output_array));
    }

    public static function isPortEnable(string $port)
    {
        $host = "127.0.0.1";
        $connection = @fsockopen($host, $port);

        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }

        return true;
    }

    public static function getEnablePort()
    {
        $port = rand(49152, 65535);

        if(self::isPortEnable($port))
            return $port;
        return self::getEnablePort();
    }
}
