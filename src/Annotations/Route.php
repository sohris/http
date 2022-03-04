<?php



namespace Sohris\Http\Annotations;

use Sohris\Http\Utils;

/**
 * @Annotation
 * 
 * @Target("METHOD")
 */
class Route
{
    /**
     * @var string
     */
    private $route;


    function __construct(array $args)
    {
        $this->route = $args['value'];
    }


    public function getRoute(){
        return $this->route;
    }
    
    public function valid($params)
    {
        return $this->route === $params;
    }

    public function getHashRoute()
    {
        return Utils::hashOfRoute($this->route);
    }
}
