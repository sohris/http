<?php



namespace Sohris\Http\Annotations;

use Sohris\Http\Exceptions\StatusHTTPException;

/**
 * @Annotation
 * 
 * @Target("METHOD")
 */
class HttpMethod
{
    /**
     * @Enum({"GET","POST", "PUT", "PATCH", "DELETE"})
     */
    private $request = "POST";


    function __construct(array $args)
    {
        $this->request = $args['value'];
    }


    public function getMethod()
    {
        return $this->request;
    }

    public function valid($params)
    {
        if (is_array($this->request)) {
            if (!in_array($params, $this->request))
                throw new StatusHTTPException("Method Not Allowed", 405);
        } else if ($this->request != $params) {
            throw new StatusHTTPException("Method Not Allowed", 405);
        }
    }
}
