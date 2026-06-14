# Examples

A runnable demo of the router. Every route below is wired to a real handler in
[`examples/Http/`](Http/), and each one demonstrates a specific feature of the routing model.

## Running

```cli
composer install
php -S 127.0.0.1:8000 examples/index.php
```

Then `curl` the URLs below (or hit them in a browser) and you'll see the matched handler's
response — or a 4xx/5xx body explaining why it didn't match.

The demo bootstrap is intentionally tiny — see [`index.php`](index.php). It configures the
router with `Config::create('Project\\Http\\')`, opts in to two features for demo purposes
(`withAdditionalMethods('propfind')` and `withCaster(new ValueObjectCaster())`), then routes
the request and dispatches the matched handler.

## Route catalogue

Each row below is one *thing the router does*. The "demonstrates" column is the point —
it's why the route is in the catalogue.

### Root

| URL | response | demonstrates |
|---|---|---|
| `GET /` | `200` `GET /` | empty path maps to the `$config->rootPathSubNamespace` (default `Home`) |
| `HEAD /` | `200` (no body) | `HEAD` falls back to the `GET` handler; body is stripped |

### Verb / Action routes (no inheritance)

An Action route is a standalone, fixed path; its parameters bind only the segments **after**
its own namespace, never anything inherited.

| URL | response | demonstrates |
|---|---|---|
| `GET /contact/daniel` | `200` `GET /contact/daniel` | Action with a trailing scalar param |
| `POST /contact/daniel` | `200` `POST /contact/daniel` | a second method on the same Action route |
| `GET /contact/email/daniel` | `200` `GET /contact/email/daniel` | nested Action — param is trailing (the *leading* form `/contact/{person}/email` is a 404) |
| `GET /users/create` | `200` `GET /users/create` | a fixed Action coexisting with the dynamic `/users/{id}` Item route |

### Collection routes (`GetAllAction`)

| URL | response | demonstrates |
|---|---|---|
| `GET /contacts` | `200` `GET /contacts` | top-level Collection |
| `GET /users` | `200` `GET /users` | top-level Collection |
| `GET /contacts/` | `200` `GET /contacts` | trailing slash is stripped before matching |

### Item routes (`GetOneAction`)

The id segment is consumed locally and made available to the handler.

| URL | response | demonstrates |
|---|---|---|
| `GET /users/1` | `200` `GET /users/{1}` | Item: a single id consumed |
| `PUT /users/1` | `200` `PUT /users/{1}` | the same URL, different verb → different handler |
| `PATCH /users/1` | `200` `PATCH /users/{1}` | as above |
| `DELETE /users/1` | `200` `DELETE /users/{1}` | as above |

### Nested RESTful chain (child inherits parent args)

A child `GetAllAction` / `GetOneAction` inherits the parent route's parameters by position.

| URL | response | demonstrates |
|---|---|---|
| `GET /states/qld` | `200` `GET /states/qld` | top-level Item |
| `GET /states/qld/suburbs` | `200` `GET /states/qld/suburbs` | child Collection inherits the `state` |
| `GET /states/qld/suburbs/emerald` | `200` `GET /states/qld/suburbs/emerald` | child Item inherits the `state` and consumes `suburb` |
| `GET /states/qld/suburbs/emerald/registered-businesses` | `200` … | further nesting; the deepest handler's variadic absorbs nothing yet |
| `GET /states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control` | `200` … | variadic absorbs the trailing filter segments |
| `POST /persons/daniel/dependents` | `200` `POST /persons/daniel/dependents` | **cross-method nesting** — `Persons\PostOneAction` doesn't exist (POST /persons/{id} has no REST meaning), but `Persons\GetOneAction` provides the parent address signature via the default `addressingFallbackMethods = ['get']`; child `Persons\Dependents\PostAllAction(string $id)` inherits `$id` from it |

### Action wins over RESTful at the same namespace

| URL | response | demonstrates |
|---|---|---|
| `GET /persons/schema` | `200` `GET /persons/schema (GetAction)` | candidate priority — `Schema\GetAction` is picked over `Schema\GetOneAction` because the URL has no id arg to bind |
| `GET /persons/daniel` | `200` `GET /persons/daniel` | when the segment isn't a sub-namespace, it falls through to the parent's `GetOneAction` |

### Typed parameters (casters)

A segment is cast to the handler parameter's type via the configured casters.

| URL | response | demonstrates |
|---|---|---|
| `GET /cards/hearts` | `200` `GET /cards/hearts` | enum: string-backed by value (`Suit::Hearts`) via the default `EnumCaster` |
| `GET /tokens/{a-valid-uuid}` | `200` `GET /tokens/{uuid}` | UUID: requires `ramsey/uuid` to be installed; otherwise the caster is inert |

### Value objects consume multiple segments (opt-in)

`Config::withCaster(new ValueObjectCaster())` lets a parameter consume one segment per
constructor parameter, recursively.

| URL | response | demonstrates |
|---|---|---|
| `GET /posts/2026/August/27` | `200` `GET /posts/2026/August/27` | `Date(Year $y, Month $m, Day $d)` consumes 3 segments — each ctor param recurses through the chain (scalar → enum → VO) |
| `GET /posts` | `200` `GET /posts` | the optional `?Date` defaults to `null` when no segments are given |

### Variadic parameters absorb trailing segments

| URL | response | demonstrates |
|---|---|---|
| `GET /archives` | `200` `GET /archives` | variadic of optional ints accepts zero args |
| `GET /archives/2026/05/15` | `200` `GET /archives/2026/5/15` | variadic absorbs the trailing ints (leading-zero `05` casts to `5`) |

### HEAD / OPTIONS auto-synthesis

| URL | response | demonstrates |
|---|---|---|
| `HEAD /` | `200` (empty body) | `HEAD` falls back to the `GET` handler |
| `OPTIONS /contacts` | `204` `Allow: GET, POST, PROPFIND, HEAD, OPTIONS` | OPTIONS is auto-synthesised — discovers every method available at the URL |

### Configurable HTTP methods (WebDAV / extensions)

| URL | response | demonstrates |
|---|---|---|
| `PROPFIND /contacts` | `200` `PROPFIND /contacts` | `Config::withAdditionalMethods('propfind')` registers a non-standard method (here, WebDAV) |

## Failing scenarios (normal client errors)

Each "failure" status is precise — these distinguish "the URL is wrong" from "the URL is well-formed but the value doesn't fit."

| URL | response | demonstrates |
|---|---|---|
| `GET /contact` | `400` Bad Request | URL is too short for a required parameter (`Contact\GetAction` needs `$person`) |
| `GET /posts/2026` | `400` Bad Request | partial value object — `Date` started consuming but ran out before its required ctor params were filled |
| `GET /archives/2026/not-a-number` | `422` Unprocessable Content | the URL matches a route, but `not-a-number` can't be cast to `int` |
| `GET /users/1/profile` | `404` Not Found | Action routes don't inherit a RESTful parent's id (only `GetAll`/`GetOne` do); use `/users/profile/1` |
| `GET /no-such-resource` | `404` Not Found | no route of that shape |
| `DELETE /contacts` | `405` `Allow: GET, POST, PROPFIND, HEAD, OPTIONS` | method discovery — handlers exist at this URL but not for `DELETE` |
| `POST /` | `405` `Allow: GET, HEAD, OPTIONS` | as above for the root |
| `CONNECT /contacts` | `405` | `CONNECT` is never an application method |
| `TRACE /contacts` | `405` | `TRACE` is never an application method |
| `DELETE /variadic-params-in-parent/act` | `405` `Allow: POST, OPTIONS` | **poisoning-proof discovery** — `GET` at this URL throws a misconfig exception, but `POST` is still discovered and advertised |

## Exceptional scenarios (developer misconfiguration — `500`)

These indicate a handler tree the developer should fix, not a bad client request. The example
bootstrap returns `500` here so you can see the exception class.

| URL | response | demonstrates |
|---|---|---|
| `GET /variadic-params-in-parent/act` | `500` `UnallowedVariadicParameter` | a parent's variadic would always absorb the trailing segments, so the child is unreachable as defined |
| `GET /persons/schema/t` | `500` `SignatureMismatch` | a nested RESTful (`GetOneAction`) handler exists but its parent collection wasn't addressed in the URL — the handler is unreachable as defined |
