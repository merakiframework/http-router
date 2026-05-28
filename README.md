# http-router
Maps HTTP requests to HTTP responses in PHP 8.4+.

## Features

- [x] root path "/" mappings
- [x] configure root path sub-namespace
- [x] GET http method
- [x] POST http method
- [x] PUT http method
- [x] DELETE http method
- [x] OPTIONS http method
- [x] PATCH http method
- [x] HEAD http method
- [x] Auto-synthesized OPTIONS handler for allowed methods at a route
- [x] Auto-synthesized HEAD handler from GET handler
- [ ] prevent alternative root path sub-namespace mapping (e.g. "/" is also available at "/home")
- [x] configure action prefix and suffixes
- [x] configure action naming conventions for RESTful routes (e.g. GetAllAction, GetOneAction, etc.)
- [x] 'static' routes (no RESTful semantics, just verb-based routing)
- [x] disambiguation of 'static' routes vs RESTful routes at same namespace (e.g. /users/create is not treated as a RESTful route with 'create' as an ID)
- [x] variadic routing (trailing parameters)
- [x] nested/child resources (with parameter 'inheritence' from parent resource)
- [x] required parameter routing
- [x] optional parameter routing
- [x] integer parameters
- [x] string parameters
- [ ] array parameters (CSV in URL segment, e.g. /users/ids/1,2,3)
- [ ] float parameters
- [ ] Enum parameters
- [x] union types (int|string)
- [ ] value-object parameters (e.g. Money, Distance, Year, Date, etc.)
- [x] allowed methods provided for 405 results
- [ ] accepted types provided for 406 results
- [ ] cache results
- [ ] logging
- [x] provide custom logger
- [ ] provide custom negotiator (for negotiating media-types/languages/etc.)
- [ ] negotiate media-types
- [ ] negotiate languages
- [ ] Concurrency support for Swoole
- [ ] Reverse routing
- [ ] route dumper
- [ ] route handler generator
- [ ] add ability to ignore route handler parameters (e.g. allow for routing to a handler with signature `function __invoke(Request $request, int $id, string $action)` where $request will be ignored for routing purposes and only $id and $action will be used to match the URL segments)

## Installation

```cli
composer install meraki/http-router
```

## Usage

Standard usage is simple. Just instantiate the router, pass the request method and request target, then handle the result:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\Router;
use Laminas\Diactoros\ServerRequestFactory;

$config = new Router\Config('Project\\Http\\');
$router = new Router($config);
$request = ServerRequestFactory::fromGlobals();

try {
	$result = $router->route($request);
} catch (Router\Exception\SignatureMismatch|Router\Exception\UnallowedVariadicParameter $e) {
	// warn about misconfigured route handler signatures (e.g. missing required parameter, variadic parameter not last, etc.)
}

switch ($result->status) {
	case 200:
		// get the matched route
		$route = $result->route;

		// access info about the matching route
		$requestHandler = $route->requestHandler;
		$invokeMethod = $route->invokeMethod;
		$params = $route->parameters;
		break;

	case 204:
		// the request was successfully processed - auto-synthesized options
		break;

	case 400:
		// the request that was malformed
		$request = $result->request;
		break;

	case 404:
		// the request that couldn't be matched
		$request = $result->request;
		break;

	case 405:
		// fully qualified class name that was built
		$allowedMethods = $result->allowedMethods;
		break;

	case 422:
		// the route that was closest to matching (e.g. if the URL was correct but a parameter value was of the wrong type)
		$closestMatches = $result->closestMatches;
		break;

	default:
		// 500 internal server error
}
```

To see some other use cases, look at the `examples` directory in the source code. For more advanced setups, check out the documentation, especially the section on configuration.

## Intentions and design decisions

1. Child resources can only be matched if there is a parent resource defined with the same HTTP method as the child

For example, the following HTTP request:

```text
POST /artists/123/songs/456
```

will only work if the following two classes exist:

```php
$parentResource = Project\Http\Artists\PostOneAction::class;
$childResource = Project\Http\Artists\Songs\PostOneAction::class;
```

The `$parentResource` will not be instantiated at any point during routing, but it must still exists and the `$childResource` must 'extend' the method signature of `$parentResource`.

For example, if `$parentResource` has the method signature `public function __invoke(int $artist)`, then `$childResource` must have a method signature of `public function __invoke(int $artist, int $song)` or `public function __invoke(int $artist, int $song, ...$args)`.

2. This library does not rely on PSR7 for request and response objects. This provides for the greatest compatibility between different HTTP implementations.
3. A 'convention over configuration' approach is taken to routing, where the URL structure and HTTP method of a request implies the fully qualified class name and method signature of the handler that should be invoked. This means that there is no need for manually defining routes in a separate file or using attributes on handler classes, which can be more error-prone and less performant.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
