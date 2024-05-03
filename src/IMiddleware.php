<?php
namespace Sohris\Http;

use Psr\Http\Message\RequestInterface;


interface IMiddleware
{
    public function __invoke(RequestInterface $request, \Closure $next);   
}