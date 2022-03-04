<?php
namespace Sohris\Http;

use Psr\Http\Message\ServerRequestInterface;


interface IMiddleware
{
    public function __invoke(ServerRequestInterface $request, \Closure $next);   
}