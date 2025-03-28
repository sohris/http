# Sohris HTTP

## Summary

Sohris HTTP is built on ReactPHP, allowing the server to handle requests asynchronously. This non-blocking architecture enables improved performance, as multiple requests can be processed simultaneously without waiting for previous ones to complete.

## Creating a Route

To generate a new route in the API, it is necessary to understand the available annotations in PHP, which help manage requests.

### Route Example

```php
namespace App\Routes\Template;

use Sohris\Http\Response;
use Sohris\Http\Router\RouterControllers\DRMRouter;
use Sohris\Http\Annotations\SessionJWT;
use Sohris\Http\Annotations\Needed;
use Sohris\Http\Annotations\HttpMethod;
use Sohris\Http\Annotations\Route;

class Teste extends DRMRouter
{
    /**
     * @Route("/template/teste/requisicao_1")
     * @SessionJWT(true)
     * @HttpMethod("POST")
     * @Needed({"param_1", "param_2"})
     */
    public static function requisicao_1(\Psr\Http\Message\RequestInterface $request)
    {        
        return Response::Json("Hello World!");
    }
}
```

## Asynchronous Execution

Sohris HTTP operates **asynchronously**, based on **ReactPHP**, allowing non-blocking request handling.

### Benefits:
- **Higher performance** – Processes multiple requests simultaneously.
- **Lower latency** – Faster responses.
- **Efficient resource usage** – Better CPU and memory utilization.

### Asynchronous Return Example with Promise

```php
use React\Promise\Promise;

public static function asyncRoute(\Psr\Http\Message\RequestInterface $request)
{
    return new Promise(function ($resolve) {
        // Simulate an asynchronous operation
        $resolve(Response::Json( "Async Response"));
    });
}
```

With this model, requests do not block execution, ensuring a highly efficient server environment.


### `@Needed`

Defines the required parameters in the request.

```php
@Needed({"param_1", "param_2"})
```

Parameters can be accessed via `REQUEST` in the request object:

```php
public static function rota_1(\Psr\Http\Message\RequestInterface $request)
{
    echo $request->REQUEST['param_1'];
    echo $request->REQUEST['param_2'];
}
```

### `@SessionJWT`

Indicates that the route requires a valid JWT session. The session is generated by the `/signin/auth/login` route and consists of a JWT token that encapsulates user information.

If the request does not contain a valid token, the server will return **error 403**.

To authenticate, send the token in the header:

```
Authorization: Bearer [TOKEN]
```

### `@HttpMethod`

Defines the allowed HTTP methods for the route:

```php
@HttpMethod("POST")
```

Supported methods:

- **POST**
- **GET**
- **OPTIONS**
- **PUT**
- **DELETE**


