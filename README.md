# http-router
Maps HTTP requests to HTTP responses in PHP 8+.

## Features

- [x] root path "/" mappings
- [x] configure root path sub-namespace
- [x] GET http method
- [ ] POST http method
- [ ] PUT http method
- [ ] DELETE http method
- [ ] OPTIONS http method
- [x] return GET request-handler if no HEAD request-handler is defined
- [ ] asterix OPTIONS "OPTIONS *" http method
- [ ] prevent alternative root path sub-namespace mapping (e.g. "/" is also available at "/home")
- [x] configure action prefix
- [x] configure action suffix
- [x] noun-based URLs (plural) (RESTful URLs)
- [x] verb-based URLs (singular) (actions)
- [x] override plural segments (exclude words from auto plural-singular conversion)
- [ ] override singular segments (exclude words from auto singular-plural conversion)
- [x] support HEAD request from a GET request handler
- [x] variadic routing (trailing parameters)
- [x] nested resources
- [x] required parameter routing
- [x] optional parameter routing
- [x] integer parameters
- [x] string parameters
- [ ] array parameters
- [ ] float parameters
- [x] allowed methods provided for 405 results
- [ ] accepted types provided for 406 results
- [ ] cache results
- [ ] logging
- [ ] provide custom inflector (for noun-plural conversions)
- [x] provide custom logger
- [ ] provide custom negotiator (for negotiating media-types/languages/etc.)
- [ ] negotiate media-types
- [ ] negotiate languages
- [ ] Concurrency support for Swoole
- [ ] Reverse routing
- [ ] Remove the need on having to define parent resource classes (caveat 1)
- [ ] route dumper (CLI)
- [ ] class creator from route (CLI)
- [ ] Enum support?
- [ ] value-object support?
- [ ] Better differentiation between when a plural or noun is needed (so overriding plural words are not needed as much)
- [x] union types (int|string)

## Installation

```cli
composer install meraki/http-router
```

## Usage

Basic configuration that will suit most small projects and that is compatible with all the PHP-FIG PSRs:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\AutoRouter;
use Laminas\Diactoros\ServerRequestFactory;

$router = new AutoRouter('Project\\Http\\');
$request = ServerRequestFactory::fromGlobals();

$result = $router->route($request);

switch ($result->status) {
	case 200:
		// get the matched route
		$route = $result->route;

		// access info about the matching route
		$requestHandler = $route->requestHandler;
		$invokeMethod = $route->invokeMethod;
		$params = $route->parameters;
		break;

	case 404:
		// the request that couldn't be matched
		$request = $result->request;
		default;

	case 405:
		// fully qualified class name that was built
		$allowedMethods = $result->allowedMethods;
		default;

	default:
		// 500 internal server error
}
```

To see some other use cases, look at the `examples` directory in the source code. For more advanced setups, check out the documentation, especially the section on configuration.

## Caveats

1. Child resources can only be matched if there is a  parent resource defined with the same HTTP method as the child

For example, the following HTTP request:

```text
POST /contact/0412345678/ping
```

will only work if the following two classes exist:

```php
$parentResource = Project\Http\Contact\PostAction::class;
$childResource = Project\Http\Contact\Ping\PostAction::class;
```

The `$parentResource` will not be instantiated at any point during  routing, but it must still exists and the `$childResource` must have the same method signature as the `$parentResource`.

## Notes

- This library made the intentional decision to not rely on PSR7 for request and response objects. This provides for the greatest compatibility between different HTTP implementations.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
