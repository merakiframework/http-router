<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router;
use Meraki\Http\Router\Result;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use Meraki\Http\Router\Exception\SignatureMismatch;
use Meraki\Http\AssertionBuilder\Result as ResultBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Executable specification mirroring the scenario catalogue documented in
 * examples/index.php. Each row is one documented behaviour, asserted against
 * the real examples/Http fixtures with the default configuration.
 *
 * Keep this in sync with the examples/index.php docblock: a new documented
 * scenario should gain a row here, and a changed status/handler should fail
 * loudly.
 */
#[CoversClass(Router::class)]
final class RouterScenariosTest extends TestCase
{
	private const NS = 'Project\\Http\\';

	/**
	 * @param string|null $handler expected matched handler (sans namespace), or null when no route matches
	 * @param array<int, mixed> $args expected bound arguments (only checked when $handler is set)
	 * @param string[]|null $allowed expected Allow list (405/204 rows), or null to skip the check
	 */
	#[Test()]
	#[DataProvider('routingScenarios')]
	public function scenario(
		string $method,
		string $url,
		int $status,
		?string $handler,
		array $args,
		?array $allowed
	): void {
		$result = new Router(self::NS)->route($method, $url);

		$assert = $this->assertResult($result)->hasStatusOf($status);

		if ($handler !== null) {
			$assert->hasRouteThat()
				->matchesRequestHandler(self::NS . $handler)
				->hasArguments($args);
		}

		if ($allowed !== null) {
			$assert->allowsMethods($allowed);
		}
	}

	/**
	 * @return array<string, array{string, string, int, ?string, array<int, mixed>, ?string[]}>
	 */
	public static function routingScenarios(): array
	{
		return [
			// --- Root path ---------------------------------------------------
			'GET / -> Home\GetAction' => ['get', '/', 200, 'Home\\GetAction', [], null],
			'HEAD / falls back to GET' => ['head', '/', 200, 'Home\\GetAction', [], null],

			// --- Collections (RouteType::Collection) -------------------------
			'GET /contacts' => ['get', '/contacts', 200, 'Contacts\\GetAllAction', [], null],
			'GET /contacts/ (trailing slash stripped)' => ['get', '/contacts/', 200, 'Contacts\\GetAllAction', [], null],
			'GET /users' => ['get', '/users', 200, 'Users\\GetAllAction', [], null],

			// --- Items (RouteType::Item), write methods ----------------------
			'GET /users/1' => ['get', '/users/1', 200, 'Users\\GetOneAction', ['1'], null],
			'PUT /users/1' => ['put', '/users/1', 200, 'Users\\PutOneAction', ['1'], null],
			'PATCH /users/1' => ['patch', '/users/1', 200, 'Users\\PatchOneAction', ['1'], null],
			'DELETE /users/1' => ['delete', '/users/1', 200, 'Users\\DeleteOneAction', ['1'], null],

			// --- Verb / Action routes (RouteType::Action) --------------------
			'GET /contact/daniel' => ['get', '/contact/daniel', 200, 'Contact\\GetAction', ['daniel'], null],
			'POST /contact/daniel' => ['post', '/contact/daniel', 200, 'Contact\\PostAction', ['daniel'], null],
			'GET /contact/email/daniel (Action, trailing param)' => ['get', '/contact/email/daniel', 200, 'Contact\\Email\\GetAction', ['daniel'], null],
			'GET /contact/daniel/email -> 404 (Action will not bind a leading segment)' => ['get', '/contact/daniel/email', 404, null, [], null],
			'GET /users/create (static route)' => ['get', '/users/create', 200, 'Users\\Create\\GetAction', [], null],
			'GET /users/profile/1 (Action, parent pass-through)' => ['get', '/users/profile/1', 200, 'Users\\Profile\\GetAction', ['1'], null],
			'GET /users/1/profile -> 404 (Action does not inherit an Item id)' => ['get', '/users/1/profile', 404, null, [], null],
			'POST /business-details/holiday-exceptions (No parent POST action)' => ['post', '/business-details/holiday-exceptions', 200, 'BusinessDetails\\HolidayExceptions\\PostAction', [], null],

			// --- Disambiguation: Action wins over Item at same namespace -----
			'GET /persons/schema -> Schema\GetAction (not GetOneAction)' => ['get', '/persons/schema', 200, 'Persons\\Schema\\GetAction', [], null],
			'GET /persons/daniel -> Persons\GetOneAction' => ['get', '/persons/daniel', 200, 'Persons\\GetOneAction', ['daniel'], null],

			// --- Nested RESTful chain (child inherits parent args) -----------
			'GET /states/qld' => ['get', '/states/qld', 200, 'States\\GetOneAction', ['qld'], null],
			'GET /states/qld/suburbs' => ['get', '/states/qld/suburbs', 200, 'States\\Suburbs\\GetAllAction', ['qld'], null],
			'GET /states/qld/suburbs/emerald' => ['get', '/states/qld/suburbs/emerald', 200, 'States\\Suburbs\\GetOneAction', ['qld', 'emerald'], null],
			'GET /states/qld/suburbs/emerald/registered-businesses' => ['get', '/states/qld/suburbs/emerald/registered-businesses', 200, 'States\\Suburbs\\RegisteredBusinesses\\GetAllAction', ['qld', 'emerald'], null],
			'GET .../registered-businesses/cleaning/pest-control (variadic, compound word)' => ['get', '/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control', 200, 'States\\Suburbs\\RegisteredBusinesses\\GetAllAction', ['qld', 'emerald', 'cleaning', 'pest-control'], null],

			// --- Variadic absorbing trailing int segments --------------------
			'GET /archives' => ['get', '/archives', 200, 'Archives\\GetAllAction', [], null],
			'GET /archives/2026' => ['get', '/archives/2026', 200, 'Archives\\GetAllAction', [2026], null],
			'GET /archives/2026/05 (leading zero)' => ['get', '/archives/2026/05', 200, 'Archives\\GetAllAction', [2026, 5], null],
			'GET /archives/2026/05/15' => ['get', '/archives/2026/05/15', 200, 'Archives\\GetAllAction', [2026, 5, 15], null],

			// --- Single-level variadic, no extension past it -----------------
			'GET /variadic-params-in-parent (no extension)' => ['get', '/variadic-params-in-parent', 200, 'VariadicParamsInParent\\GetAction', [], null],
			'GET /variadic-params-in-parent/404/405 (variadic absorbs)' => ['get', '/variadic-params-in-parent/404/405', 200, 'VariadicParamsInParent\\GetAction', [404, 405], null],

			// --- 400 Bad Request: URL too short for a required parameter ------
			'GET /contact (missing required $person)' => ['get', '/contact', 400, null, [], null],

			// --- 422 Unprocessable: value present but un-castable ------------
			'GET /archives/2026/not-a-number' => ['get', '/archives/2026/not-a-number', 422, null, [], null],

			// --- 404 Not Found: no route of that shape -----------------------
			'GET /no-such-resource' => ['get', '/no-such-resource', 404, null, [], null],

			// --- 405 Method Not Allowed (+ Allow list) -----------------------
			'DELETE /contacts' => ['delete', '/contacts', 405, null, [], ['get', 'head', 'post', 'options']],
			'POST / (only GET defined at root)' => ['post', '/', 405, null, [], ['get', 'head', 'options']],
			'CONNECT /contacts (unsupported method)' => ['connect', '/contacts', 405, null, [], ['get', 'head', 'post', 'options']],
			'TRACE /contacts (unsupported method)' => ['trace', '/contacts', 405, null, [], ['get', 'head', 'post', 'options']],
			'DELETE /variadic-params-in-parent/act (poisoning-proof discovery)' => ['delete', '/variadic-params-in-parent/act', 405, null, [], ['post', 'options']],

			// --- 204 OPTIONS auto-synthesis ----------------------------------
			'OPTIONS /contacts (auto-synthesised)' => ['options', '/contacts', 204, null, [], ['get', 'head', 'post', 'options']],
		];
	}

	#[Test()]
	#[DataProvider('throwingScenarios')]
	public function throws_for_misconfigured_handler_trees(string $method, string $url, string $exceptionClass): void
	{
		$this->expectException($exceptionClass);

		new Router(self::NS)->route($method, $url);
	}

	/**
	 * @return array<string, array{string, string, class-string<\Throwable>}>
	 */
	public static function throwingScenarios(): array
	{
		return [
			'variadic parent blocks child route' => [
				'get', '/variadic-params-in-parent/act', UnallowedVariadicParameter::class,
			],
			'nested RESTful handler unreachable without addressed parent' => [
				'get', '/persons/schema/t', SignatureMismatch::class,
			],
		];
	}

	#[Test()]
	public function webdav_method_routes_once_registered_via_config(): void
	{
		$config = Config::create(self::NS)->withAdditionalMethods('propfind');

		$result = new Router($config)->route('propfind', '/contacts');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::NS . 'Contacts\\PropfindAllAction');
	}

	private function assertResult(Result $sut): ResultBuilder
	{
		return new ResultBuilder($this, $sut);
	}
}
