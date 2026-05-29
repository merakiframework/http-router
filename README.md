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

1. **RESTful child resources require a parent handler for the same HTTP method, whose signature the child extends.**

   For example, the following HTTP request:

   ```text
   POST /artists/123/songs/456
   ```

   will only work if the following two classes exist:

   ```php
   $parentResource = Project\Http\Artists\PostOneAction::class;
   $childResource = Project\Http\Artists\Songs\PostOneAction::class;
   ```

   The `$parentResource` is never instantiated during routing, but it must exist, and `$childResource` must 'extend' its method signature. If `$parentResource` is `public function __invoke(int $artist)`, then `$childResource` must be `public function __invoke(int $artist, int $song)` or `public function __invoke(int $artist, int $song, ...$args)`. (This applies to RESTful types only — `Action` routes are standalone; see decision 5.)

2. **No PSR-7 dependency.** The library does not rely on PSR-7 for request/response objects, for the greatest compatibility between HTTP implementations.

3. **Convention over configuration.** The URL structure and HTTP method imply the handler's fully-qualified class name and method signature. There are no route files and no attributes on handlers — both of which are more error-prone and less performant.

4. **The class name encodes intent — there is no inflection, pluralization, or URL guessing.** The class-name *suffix* alone determines the route type (`GetAllAction` → Collection, `GetOneAction` → Item, `GetAction` → Action). A compound resource like `RegisteredBusinesses` needs no special inflection rule. Because the mapping is mechanical and total, handler names are deterministic and reversible (a prerequisite for future reverse-routing).

5. **`Action` (static) routes are standalone and take priority.** An `Action` route binds only the segments that follow its own namespace; it never inherits a parent's arguments, and it wins over a RESTful handler at the same namespace. This makes its URL fully deterministic. Reach for it for verbs or fixed overrides — e.g. a static `/users/create` living alongside a dynamic `/users/{id}`, or a vanity `/users/{username}`. (See `examples/Http/Music/TrackInfo/GetAction.php` for the ambiguity that mixing static and RESTful routing can create.)

6. **`string` is the universal segment type; only narrowing types can fail.** A `string` parameter accepts any URL segment and never produces a `422`. Only narrowing types (`int`, `float`, enums, value-objects) can reject a value as unprocessable.

7. **HTTP methods are handled deliberately.** `CONNECT` and `TRACE` are never application methods (always `405`). `OPTIONS` is auto-synthesised (`204`) advertising the allowed methods, and `HEAD` is derived from `GET`. The supported-method set is configurable via `Config::withAdditionalMethods()` for WebDAV (`PROPFIND`, `MKCOL`, …) or other extensions.

8. **Method discovery is "poisoning-proof."** When building the `Allow` list for a `405`/`OPTIONS`, a single misconfigured handler for one method does not hide the other valid methods available at the same URL.

9. **Misconfigurations surface as exceptions, not silent mis-routes.** A variadic parent that would permanently shadow a child route throws `UnallowedVariadicParameter`; an unreachable nested RESTful handler throws `SignatureMismatch`. These are developer errors in the handler tree, so they are raised rather than quietly returning a `404`.

10. **Status codes are precise.** `400` = the URL is too short for a required parameter (structurally malformed); `404` = no route of that shape (including *too many* segments); `422` = a route matched but a value can't cast to its parameter's type.

### How routing works

Take `$router->route('GET', '/states/qld/suburbs/emerald')`:

1. **Normalise.** The method is lower-cased and the request target is parsed and split into segments — `['states', 'qld', 'suburbs', 'emerald']`. An empty path (`/` or `""`) targets the root resource (`rootPathSubNamespace`, default `Home`).

2. **Check the method.** If it isn't in the supported set, short-circuit to `405` (or `404` if the URL has no handlers at all).

3. **Walk the segments, left to right.** For each segment the router tries to *extend* the current namespace by appending the class-name form of the segment. If an action class exists at that extended namespace, the segment is a **namespace component** and begins a new level; otherwise it is an **argument** collected against the current level:

   | segment | extends to | role |
   |---------|-----------|------|
   | `states`  | `States`              | namespace |
   | `qld`     | `States\Qld`? (none)  | argument of the `States` level |
   | `suburbs` | `States\Suburbs`      | namespace |
   | `emerald` | `States\Suburbs\Emerald`? (none) | argument of the `States\Suburbs` level |

4. **Match a handler at each level.** Candidate class suffixes are tried in priority order — **`Action` → Collection (`AllAction`) → Item (`OneAction`)** — and the first whose signature fits the bound arguments wins. Trying `Action` first is what lets `/persons/schema` resolve to `Persons\Schema\GetAction` even when `Persons\Schema\GetOneAction` also exists.

5. **Resolve the arguments — in this order:**
   1. **Inherited first (RESTful only).** A Collection/Item child starts with the parameters and bound arguments inherited from its matched parent (`qld` flows down to `States\Suburbs\GetOneAction`). An `Action` route inherits nothing.
   2. **Then local segments.** The level's own segments fill the remaining parameters, left to right. Each segment is cast to the parameter's declared type; if no declared type accepts it, the candidate becomes a `422` candidate.
   3. **Then the variadic.** Any leftover segments must be absorbed by a trailing variadic parameter (`...$args`); if there's no variadic, the candidate doesn't fit.

   So `/states/qld/suburbs/emerald` binds `['qld', 'emerald']` to `States\Suburbs\GetOneAction(string $state, string $suburb)` — `qld` inherited from the parent, `emerald` from the local segment.

6. **Build the result.** A matched chain becomes a `200` carrying the primary (deepest) handler and its bound arguments. A failure becomes `400` / `404` / `422`; method discovery produces `405` or an auto-synthesised `204` for `OPTIONS`.

### Route types and their intentions

| Type | Class suffix | Intent | Inherits parent args? |
|------|--------------|--------|-----------------------|
| **Collection** | `GetAllAction` | Operate on a whole collection (list, create-into). | Yes |
| **Item** | `GetOneAction` | Operate on a single addressed member of a collection; consumes the id segment. | Yes |
| **Action** | `GetAction` | A verb / static / vanity route that bypasses RESTful semantics. Binds only its own trailing segments and wins over RESTful handlers at the same namespace. | No |

Collection and Item are the **RESTful** types: they model resources and chain together (`/states/{state}/suburbs/{suburb}`), each inheriting its parent's identifying arguments. `Action` is the **escape hatch**: a fixed route that behaves "as if the file existed at that path," for cases the RESTful conventions shouldn't own.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
