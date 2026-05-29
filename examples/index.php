<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\Router;
use Meraki\Http\Router\Config;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

$config = Config::create('Project\\Http\\')->withAdditionalMethods('propfind');
$router = new Router($config);
$request = ServerRequestFactory::fromGlobals();

/**
 * ===================================================================
 * HAPPY-PATH SCENARIOS - these are the intended use cases that the router should handle correctly, demonstrating the various features and capabilities of the router
 * ===================================================================
 *
 * Root path — empty URL (or "/" URL) maps to the $config->rootPathSubNamespace.
 *   GET /		-> Home\GetAction()
 *   HEAD /		-> falls back to GetAction
 *
 * Verb/Action routes (no inheritance — an Action binds only the segments AFTER its own namespace):
 *   GET /contact/{person}			-> Contact\GetAction($person)
 *   GET /contact/email/{person}	-> Contact\Email\GetAction($person)	(param is trailing; /contact/{person}/email is a 404)
 *   POST /contact/{person}			-> Contact\PostAction($person)
 *
 * Collection routes (RouteType::Collection):
 *   GET /contacts		-> Contacts\GetAllAction()
 *   GET /users			-> Users\GetAllAction()
 *
 * Item routes (RouteType::Item, ID consumed locally):
 *   GET /users/{id}		-> Users\GetOneAction($id)
 *   PUT /users/{id}		-> Users\PutOneAction($id)
 *   PATCH /users/{id}		-> Users\PatchOneAction($id)
 *   DELETE /users/{id}		-> Users\DeleteOneAction($id)
 *
 * Nested routes that are NOT RESTful (no parent-child argument inheritance):
 *   GET /users/profile/{id}	-> Users\Profile\GetAction($id)	(Has same signature as GetOneAction but is not enforced as it's a
 * 																'static' route, otherwise it would be a 422 because the URL doesn't
 * 																satisfy the implied signature of GetOneAction which would require
 * 																an id before 'profile' and after 'users', e.g. "/users/{id}/profile/{id}")
 *   GET /users/create			-> Users\Create\GetAction()		(This is a 'statically' defined route)
 *
 * Static (Action) routing vs RESTful routing:
 *   A static route bypasses dynamic/RESTful matching entirely — it matches a fixed path as if the file
 *   existed there, and binds only the segments that FOLLOW its own namespace (it never inherits a
 *   parent's args). Prefer it for verbs, or to override a dynamic route with a fixed one — e.g. a vanity
 *   item route /users/{username} alongside a static /users/create the app always needs. Mixing the two
 *   can be ambiguous: see examples/Http/Music/TrackInfo/GetAction.php for a worked example.
 *
 * Nested RESTful chain (child inherits parent's args):
 *   GET /states/{state}															-> States\GetOneAction($state)
 *   GET /states/{state}/suburbs													-> States\Suburbs\GetAllAction($state)
 *   GET /states/{state}/suburbs/{suburb}											-> States\Suburbs\GetOneAction($state, $suburb)
 *   GET /states/{state}/suburbs/{suburb}/registered-businesses						-> States\Suburbs\RegisteredBusinesses\GetAllAction($state, $suburb)
 *   GET /states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control	-> States\Suburbs\RegisteredBusinesses\GetAllAction('qld', 'emerald', 'cleaning', 'pest-control')
 *
 * Sub-resource Action (parent skipped — no ID required):
 *   GET /persons/schema		-> Persons\Schema\GetAction()		('static' routes wins over 'restful' routes at same namespace)
 *   GET /persons/daniel		-> Persons\GetOneAction('daniel')	(id falls through to parent GetOneAction)
 *   (An Action child does NOT inherit a RESTful parent's id — only GetAll/GetOne do.
 *    So /users/{id}/profile is a 404; the profile route is reached via /users/profile/{id} above.)
 *
 * Typed parameters (the segment is cast to the handler parameter's type by the configured casters):
 *   GET /cards/hearts			-> Cards\GetOneAction(Suit::Hearts)		(enum: string-backed by value; pure enums by case name, case-insensitive)
 *   GET /tokens/{uuid}			-> Tokens\GetOneAction($uuid)			(ramsey/uuid UuidInterface — install ramsey/uuid yourself; the caster is inert otherwise)
 *   (built-in casters: string, int, float, array (CSV), enum, uuid. Register your own via Config::withCaster().)
 *
 * Value objects consume one segment per constructor parameter, recursively (opt-in: Config::withCaster(new ValueObjectCaster())):
 *   GET /posts/2026/August/27	-> Posts\GetAllAction(new Date(new Year(2026), Month::August, new Day('27')))
 *   												(Date(Year $y, Month $m, Day $d) — each ctor param is itself cast through the chain)
 *   GET /posts					-> Posts\GetAllAction(null)				(the optional ?Date defaults to null when no segments are given)
 *   GET /posts/2026			-> 400									(partial value object: required constructor segments are missing)
 *
 * Variadic absorbing trailing segments (optional ints):
 *   GET /archives				-> Archives\GetAllAction()
 *   GET /archives/2026			-> Archives\GetAllAction(2026)
 *   GET /archives/2026/05		-> Archives\GetAllAction(2026, 5)		(leading zero handled)
 *   GET /archives/2026/05/15	-> Archives\GetAllAction(2026, 5, 15)
 *
 * Compound-word as resource collection (the action name defines the intent of the route, not the URL):
 *   GET /states/{state}/suburbs/{suburb}/registered-businesses/{filters...}	-> States\Suburbs\RegisteredBusinesses\GetAllAction(...inherited..., ...$filters)
 *
 * Trailing slash is stripped before matching:
 *   GET /contacts/		-> Contacts\GetAllAction()	(same as /contacts)
 *
 * HEAD falls back to GET when no HeadAction is defined:
 *   HEAD /contacts		-> Contacts\GetAllAction()
 *
 * OPTIONS auto-synthesised (204 No Content + Allow:) when no OptionsAction defined:
 *   OPTIONS /contacts		-> 204, Allow: get, head, post, options
 *
 * Supporting additional HTTP methods (like WebDAV) is easy to configure:
 *   $config = Config::create('Project\\Http\\')->withAdditionalMethods('propfind', 'proppatch', 'mkcol', 'copy', 'move', 'lock', 'unlock');
 *   PROPFIND /contacts		-> Contacts\PropfindAllAction()
 *
 *
 * ===================================================================
 * NORMAL FAILING SCENARIOS — these are normal bad requests that the router should handle gracefully with appropriate status codes
 * ===================================================================
 *
 * 400 Bad Request — URL is missing a required parameter or has an incompatible structure:
 *   GET /contact	-> 400 		(Contact\GetAction requires `string $person`, URL provides none. The handler
 * 								exists but the URL doesn't satisfy the signature.)
 *
 * 422 Unprocessable Content — URL is well-formed (matched to a route) but a segment cannot match the parameter's accepted types:
 *   GET /archives/2026/not-a-number	-> 422		(Archives\GetAllAction's $month is `?int`. "not-a-number" can't cast.
 * 													The route matched but the value is unprocessable — distinct from 400
 * 													which is reserved for structurally-malformed URLs.)
 *
 * 404 Not Found — URL doesn't map to any handler:
 *   GET /no-such-resource		-> 404
 *   GET /users/1/profile		-> 404		(Profile is an Action; it won't bind the leading `1`. Only RESTful
 * 											GetAll/GetOne children inherit a parent's args — use /users/profile/1.)
 *   GET /contact/daniel/email	-> 404		(Email is an Action; it won't bind the leading `daniel` — use /contact/email/daniel.)
 *
 * 405 Method Not Allowed — handlers exist but not for the requested method:
 *   DELETE /contacts		-> 405, Allow: get, head, post, options
 *   POST /					-> 405, Allow: get, head, options
 *
 * 405 Method Not Allowed — CONNECT and TRACE are not application methods:
 *   CONNECT /contacts		-> 405
 *   TRACE /contacts		-> 405
 *
 * 405 discovery is poisoning-proof — one misconfigured method doesn't prevent discovery of other valid methods at the same URL:
 *   DELETE /variadic-params-in-parent/act		-> 405, Allow: post, options	(GET probe throws UnallowedVariadicParameter, but POST is
 * 																				discovered via VariadicParamsInParent\PostAction +
 * 																				Act\PostAction. GET is NOT advertised in Allow: because
 * 																				it's genuinely broken at that URL.)
 *
 *
 * ==================================================================
 * EXCEPTIONAL FAILING SCENARIOS — these indicate configuration mistakes that the developer should fix, not just bad URLs
 * ==================================================================
 *
 * UnallowedVariadicParameter — child route is unreachable because the parent has a variadic parameter that would
 * 								absorb all trailing segments, so the child route is effectively dead code:
 *   GET /variadic-params-in-parent/act		-> throws		(VariadicParamsInParent\GetAction has `int ...$params` that would
 * 															absorb all trailing segments. Defining VariadicParamsInParent\Act\GetAction
 * 															is effectively dead code — the variadic would always win,
 * 															so Act\GetAction is unreachable.)
 *
 *   GET /variadic-params-in-parent				-> Variadic\GetAction()			(no extension, no throw)
 *   GET /variadic-params-in-parent/404/405 	-> Variadic\GetAction(404, 405) (variadic absorbs both)
 *
 * SignatureMismatch — RESTful handlers defined, but parent was not addressed in the URL:
 *   GET /persons/schema/t	-> throws	(Persons\Schema\GetOneAction takes `string $person` and implies an inherited parent
 * 										context. The URL didn't provide a person ID before 'schema', so the handler is
 * 										unreachable as-defined. To reach it properly, the URL would need to be /persons/{personid}/schema/{id},
 * 										with the handler signature matching the inherited param chain.)
 */
$result = $router->route('get' ?? $request->getMethod(), $request->getRequestTarget());

switch ($result->status) {
	case 200:
		$route = $result->route;
		$class = new $route->requestHandler;
		$response = call_user_func_array([$class, $route->invokeMethod], $route->arguments);
		(new SapiEmitter())->emit($response);
		break;

	default:
		var_dump($result);die();
}
