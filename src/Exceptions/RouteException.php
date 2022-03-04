<?php
namespace Sohris\Http\Exceptions;

class RouteException extends \Exception
{
    public $code;

    public $message;

    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        $this->code = $code;
        $this->message = $message;

        parent::__construct($message, $code, $previous);

    }
}
