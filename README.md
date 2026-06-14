# http-router
Maps HTTP requests to HTTP responses in PHP 8.4+.

## Features

**Routing model**
- Root path `/` mapping (configurable sub-namespace, default `Home`)
- RESTful route types from the class name alone — no inflection, no route files: `GetAllAction` (Collection), `GetOneAction` (Item), `GetAction` (Action / verb / static)
- Nested resources with parameter inheritance from the parent route (e.g. `/states/{state}/suburbs/{suburb}`)
- Disambiguation of static (`Action`) and RESTful routes at the same namespace — `/users/create` resolves to `Users\Create\GetAction`, not `Users\GetOneAction('create')`
- Variadic routing (trailing parameters absorbed by `...$args`)
- Configurable action prefix, suffix, and singular/plural indicators via `Router\Config`

**HTTP methods**
- `GET`, `POST`, `PUT`, `PATCH`, `DELETE` mapped from method-prefixed class names
- `HEAD` auto-derived from `GET` (body stripped at the SAPI layer)
- `OPTIONS` auto-synthesised (`204` + `Allow:`) listing every method available at the URL
- Additional methods (WebDAV, etc.) via `Config::withAdditionalMethods('propfind', …)`
- Allowed-methods list returned on `405`; method discovery is **poisoning-proof** (one misconfigured method doesn't hide the others)

**Parameters (via the `Caster` system)**
- Required, optional, and variadic parameters
- Built-in types: `string` (universal — never fails), `int`, `float`, `array` (CSV), enums (backed by value; pure enums by case name, case-insensitive), `UuidInterface` (requires `ramsey/uuid`)
- Union types (e.g. `int|string`) — first matching type wins; the universal `string` caster takes precedence in a union
- Value-object parameters (constructor-driven, arbitrary nesting — `Date(Year, Month, Day)` consumes one segment per leaf) — opt-in via `Config::withCaster(new ValueObjectCaster())`
- Custom parameter types: register your own `Caster` via `Config::withCaster()`

**Status semantics**
- `400` URL too short for a required parameter (or a value object's required ctor params)
- `404` no route of that shape (including too-many segments)
- `405` method not allowed, with `Allow:` populated
- `422` route matched but a value couldn't cast to its parameter's type
- `204` auto-synthesised `OPTIONS` response
- Misconfigured handler trees throw (`UnallowedVariadicParameter`, `SignatureMismatch`) instead of silently mis-routing — surface the developer error

**Extensibility**
- Custom PSR-3 logger via `Config::withLogger()`
- `Config` is immutable; all extension points are pluggable `Caster` implementations or `with*()` config methods

## Roadmap

What's coming after alpha, grouped roughly by theme:

**Content negotiation** (planned for the negotiation milestone)
- Pluggable media-type / language negotiator
- `406 Not Acceptable` with the accepted types in the response

**Observability**
- Built-in logging hooks at route-resolution boundaries (the custom logger already plugs in via `Config::withLogger()`; the router doesn't emit log events yet)

**Performance**
- Optional route/reflection cache (route resolution today does `class_exists` per candidate + reflection per matched handler — a request-scoped cache is the obvious win)

**Routing extras**
- Prevent alternative root-path aliasing (so `/` and `/home` aren't both routable to `Home\GetAction`)
- Ignore non-URL handler parameters — e.g. let a handler typed `__invoke(Request $request, int $id)` skip `$request` for routing purposes and only consume `$id` from the URL

**Tooling**
- Reverse routing (build a URL from a handler class + arguments)
- Route dumper (enumerate every routable URL from the handler tree)
- Route-handler generator (scaffold a handler class for a given URL shape)

**Runtime**
- Concurrency support for Swoole (the router is stateless — likely already works, but needs verification + a documented setup)

## Installation

```cli
composer install meraki/http-router
```

## Usage

Instantiate the router, pass the request method and request target, then handle the result.

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\Router;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\Exception\SignatureMismatch;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use Laminas\Diactoros\ServerRequestFactory;

$router = new Router(Config::create('Project\\Http\\'));
$request = ServerRequestFactory::fromGlobals();

try {
	$result = $router->route($request->getMethod(), $request->getRequestTarget());
} catch (UnallowedVariadicParameter | SignatureMismatch $e) {
	// Misconfigured handler tree (a variadic parent that shadows a child, or a
	// nested RESTful handler with no addressed parent). These are developer
	// errors — fix the handler classes, don't ignore.
}

switch ($result->status) {
	case 200:
		$route = $result->route;
		// $route->requestHandler is the matched class-string,
		// $route->invokeMethod the method to call (default '__invoke'),
		// $route->arguments the bound parameters in order.
		$handler = new ($route->requestHandler)();
		$response = $handler->{$route->invokeMethod}(...$route->arguments);
		break;

	case 204:
		// Auto-synthesised OPTIONS: $result->allowedMethods lists every method
		// available at this URL — emit them in the Allow header.
		break;

	case 400:
		// URL is too short for a required parameter (or a value object's
		// required constructor segments are missing).
		break;

	case 404:
		// No route of that shape.
		break;

	case 405:
		// Handlers exist at this URL but not for the requested method —
		// $result->allowedMethods carries the Allow list.
		break;

	case 422:
		// The route was matched but a segment couldn't cast to its parameter's
		// type (e.g. "abc" -> int).
		break;
}
```

`route()` deliberately takes the **method and request target as strings**, not a request object, so the router doesn't depend on any particular HTTP framework. To support content negotiation later, a small request-context object may be added.

See [`examples/`](examples/) for a runnable demo of every routing behaviour; the [examples README](examples/README.md) catalogues each URL with what it demonstrates.

## Intentions and design decisions

1. **A nested RESTful child needs a parent handler that can address it. The child inherits the matched parent's signature.**

   At each non-terminal namespace level in the URL, the router needs *some* handler whose signature consumes the local args — that handler defines the parameter chain the nested child inherits. By default the router tries the **request method first**; if that finds nothing, it falls back to the methods in `Config::$addressingFallbackMethods` (defaults to `['get']` — `GET /resource/{id}` is the canonical REST way to address an item). The matched parent is used **only for signature inheritance** and is never invoked — only the deepest (primary) match is dispatched.

   For example, this request:

   ```text
   POST /persons/123/dependents
   ```

   resolves to `Project\Http\Persons\Dependents\PostAllAction(string $id)` as long as `Project\Http\Persons\GetOneAction(string $id)` exists to address the person — you don't need to define a phantom `Project\Http\Persons\PostOneAction` (which would be semantically wrong; `POST /persons/{id}` has no natural REST meaning).

   If you want the stricter pre-feature behaviour (parent must match the request method exactly), clear the fallback list: `Config::create($ns)->withAddressingFallbackMethods()`.

   (This applies to RESTful types only — `Action` routes are standalone; see decision 5.)

2. **No PSR-7 dependency.** The library does not rely on PSR-7 for request/response objects, for the greatest compatibility between HTTP implementations.

3. **Convention over configuration.** The URL structure and HTTP method imply the handler's fully-qualified class name and method signature. There are no route files and no attributes on handlers — both of which are more error-prone and less performant.

4. **The class name encodes intent — there is no inflection, pluralization, or URL guessing.** The class-name *suffix* alone determines the route type (`GetAllAction` → Collection, `GetOneAction` → Item, `GetAction` → Action). A compound resource like `RegisteredBusinesses` needs no special inflection rule. Because the mapping is mechanical and total, handler names are deterministic and reversible (a prerequisite for future reverse-routing).

5. **`Action` (static) routes are standalone and take priority.** An `Action` route binds only the segments that follow its own namespace; it never inherits a parent's arguments, and it wins over a RESTful handler at the same namespace. This makes its URL fully deterministic. Reach for it for verbs or fixed overrides — e.g. a static `/users/create` living alongside a dynamic `/users/{id}`, or a vanity `/users/{username}`. (See `examples/Http/Music/TrackInfo/GetAction.php` for the ambiguity that mixing static and RESTful routing can create.)

6. **`string` is the universal segment type; only narrowing types can fail.** A `string` parameter accepts any URL segment and never produces a `422`. Only narrowing types (`int`, `float`, enums, value-objects) can reject a value as unprocessable.

7. **HTTP methods are handled deliberately.** `CONNECT` and `TRACE` are never application methods (always `405`). `OPTIONS` is auto-synthesised (`204`) advertising the allowed methods, and `HEAD` is derived from `GET`. The supported-method set is configurable via `Config::withAdditionalMethods()` for WebDAV (`PROPFIND`, `MKCOL`, …) or other extensions.

8. **Method discovery is "poisoning-proof."** When building the `Allow` list for a `405`/`OPTIONS`, a single misconfigured handler for one method does not hide the other valid methods available at the same URL.

9. **Misconfigurations surface as exceptions, not silent mis-routes.** A variadic parent that would permanently shadow a child route throws `UnallowedVariadicParameter`; an unreachable nested RESTful handler throws `SignatureMismatch`. These are developer errors in the handler tree, so they are raised rather than quietly returning a `404`.

10. **Status codes are precise.** `400` = the URL is too short for a required parameter (structurally malformed); `404` = no route of that shape (including *too many* segments); `422` = a route matched but a value can't cast to its parameter's type.

11. **URLs are matched case-insensitively.** The whole request target is lower-cased before matching, so `/Users/1` and `/users/1` resolve to the same handler. The lower-cased segment is also what reaches the caster, so a pure (unbacked) enum's case-name match (e.g. `Month::August`) is intentionally **case-insensitive**. Use lower-case for string-backed enum values and URL-friendly value-object inputs; if you need original case preserved in a bound value, type the parameter as `string` (universal) rather than relying on case for matching.

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
