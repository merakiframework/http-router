<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router;
use Meraki\Http\RouteType;
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
 * Tests for the class-driven Router. The fixtures under examples/Http/ are
 * the canonical demonstration set; each scenario maps to an example.
 */
#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
	private const DEFAULT_TEST_FIXTURES_NAMESPACE = 'Project\\Http\\';

	// ------------------------------------------------------------------
	// Construction
	// ------------------------------------------------------------------

	#[Test()]
	public function default_config_is_created_with_correct_namespace(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$this->assertEquals('Project\\Http', $sut->config->namespace);
	}

	// ------------------------------------------------------------------
	// Root path
	// ------------------------------------------------------------------

	#[Test()]
	#[DataProvider('rootPathMappings')]
	public function root_path_routes_to_home_handler(string $method, string $target, string $expectedClass): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($method, $target);

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass);
	}

	public static function rootPathMappings(): array
	{
		return [
			'GET /' => ['get', '/', 'Home\\GetAction'],
			'HEAD / falls back to GET' => ['head', '/', 'Home\\GetAction'],
		];
	}

	// ------------------------------------------------------------------
	// URL parsing
	// ------------------------------------------------------------------

	#[Test()]
	public function trailing_slash_is_stripped(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/contacts/');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Contacts\\GetAllAction');
	}

	// ------------------------------------------------------------------
	// Simple routing per class-name semantics
	// ------------------------------------------------------------------

	#[Test()]
	#[DataProvider('simpleRoutes')]
	public function routes_per_class_name_semantics(
		string $method,
		string $target,
		string $expectedClass,
		array $expectedArgs,
		RouteType $expectedType
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($method, $target);

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasArguments($expectedArgs);

		$this->assertSame($expectedType, $result->route?->type);
	}

	public static function simpleRoutes(): array
	{
		return [
			'Collection: GET /contacts -> Contacts\GetAllAction' => [
				'get', '/contacts', 'Contacts\\GetAllAction', [], RouteType::Collection,
			],
			'Item: GET /users/1 -> Users\GetOneAction(1)' => [
				'get', '/users/1', 'Users\\GetOneAction', ['1'], RouteType::Item,
			],
			'Action: GET /contact/daniel -> Contact\GetAction(daniel)' => [
				'get', '/contact/daniel', 'Contact\\GetAction', ['daniel'], RouteType::Action,
			],
			'Action sub-route: GET /contact/daniel/email -> Contact\Email\GetAction(daniel)' => [
				'get', '/contact/daniel/email', 'Contact\\Email\\GetAction', ['daniel'], RouteType::Action,
			],
		];
	}

	// ------------------------------------------------------------------
	// Class-name intent disambiguation
	// ------------------------------------------------------------------

	#[Test()]
	public function specific_sub_route_takes_priority_when_get_action_exists(): void
	{
		// /persons/schema -> Persons\Schema\GetAction (no rules) even though
		// Persons\Schema\GetOneAction also exists in the fixtures. The
		// candidate-order rule (Action -> Collection -> Item) picks GetAction
		// because the URL has no ID arg.
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/persons/schema');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Persons\\Schema\\GetAction')
			->hasNoArguments();

		$this->assertSame(RouteType::Action, $result->route?->type);
	}

	#[Test()]
	public function id_segment_after_plural_resource_falls_through_to_get_one_action(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/persons/daniel');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Persons\\GetOneAction')
			->hasArguments(['daniel']);

		$this->assertSame(RouteType::Item, $result->route?->type);
	}

	#[Test()]
	public function compound_word_collection_routes_without_inflection_rule(): void
	{
		// Headline test for the class-driven approach: compound words like
		// "registered-businesses" don't need withInflectionRule() — the class
		// name (RegisteredBusinesses\GetAllAction) declares the collection
		// semantics, and trailing segments fall through to the variadic param.
		$expectedTarget = '/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control';
		$expectedClass = 'States\\Suburbs\\RegisteredBusinesses\\GetAllAction';
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', $expectedTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasArguments(['qld', 'emerald', 'cleaning', 'pest-control']);

		$this->assertSame(RouteType::Collection, $result->route?->type);
	}

	// ------------------------------------------------------------------
	// Parent / child chain
	// ------------------------------------------------------------------

	#[Test()]
	public function parent_args_are_inherited_by_child_route(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/users/1/profile');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Users\\Profile\\GetAction')
			->hasArguments(['1']);
	}

	// ------------------------------------------------------------------
	// Variadic absorbing trailing segments
	// ------------------------------------------------------------------

	#[Test()]
	#[DataProvider('variadicAbsorbingScenarios')]
	public function trailing_segments_are_absorbed_by_variadic_parameter(
		string $target,
		array $expectedArgs
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', $target);

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Archives\\GetAllAction')
			->hasArguments($expectedArgs);
	}

	public static function variadicAbsorbingScenarios(): array
	{
		return [
			'/archives -> []' => ['/archives', []],
			'/archives/2026 -> [2026]' => ['/archives/2026', [2026]],
			'/archives/2026/05 (leading-zero month) -> [2026, 5]' => ['/archives/2026/05', [2026, 5]],
			'/archives/2026/05/15 -> [2026, 5, 15]' => ['/archives/2026/05/15', [2026, 5, 15]],
		];
	}

	// ------------------------------------------------------------------
	// HTTP write methods
	// ------------------------------------------------------------------

	#[Test()]
	#[DataProvider('writeMethodsAndHandlers')]
	public function routes_to_handler_for_write_http_methods(
		string $method,
		string $target,
		string $expectedClass,
		array $expectedArgs
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($method, $target);

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasArguments($expectedArgs);
	}

	public static function writeMethodsAndHandlers(): array
	{
		return [
			'POST /contact/daniel' => ['post', '/contact/daniel', 'Contact\\PostAction', ['daniel']],
			'PUT /users/1' => ['put', '/users/1', 'Users\\PutOneAction', ['1']],
			'PATCH /users/1' => ['patch', '/users/1', 'Users\\PatchOneAction', ['1']],
			'DELETE /users/1' => ['delete', '/users/1', 'Users\\DeleteOneAction', ['1']],
		];
	}

	// ------------------------------------------------------------------
	// HEAD fallback
	// ------------------------------------------------------------------

	#[Test()]
	public function head_falls_back_to_get_handler(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('head', '/contacts');

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch('head')
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Contacts\\GetAllAction');
	}

	// ------------------------------------------------------------------
	// OPTIONS auto-synthesis
	// ------------------------------------------------------------------

	#[Test()]
	public function options_request_is_auto_synthesised_when_no_options_handler_exists(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('options', '/contacts');

		$this->assertResult($result)
			->hasStatusOf(204)
			->usedMethodForMatch('options')
			->allowsMethods(['get', 'head', 'post', 'options']);
	}

	#[Test()]
	public function options_returns_not_found_when_url_has_no_handlers_at_all(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('options', '/no-such-resource');

		$this->assertResult($result)
			->hasStatusOf(404);
	}

	// ------------------------------------------------------------------
	// Unsupported methods (CONNECT, TRACE)
	// ------------------------------------------------------------------

	#[Test()]
	#[DataProvider('unsupportedMethods')]
	public function returns_method_not_allowed_for_unsupported_http_methods(string $unsupportedMethod): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($unsupportedMethod, '/contacts');

		$this->assertResult($result)
			->hasStatusOf(405)
			->usedMethodForMatch($unsupportedMethod)
			->allowsMethods(['get', 'head', 'post', 'options']);
	}

	public static function unsupportedMethods(): array
	{
		return [
			'CONNECT' => ['connect'],
			'TRACE' => ['trace'],
		];
	}

	// ------------------------------------------------------------------
	// Method-not-allowed with allowed methods
	// ------------------------------------------------------------------

	#[Test()]
	public function lists_allowed_methods_when_method_not_supported_at_url(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('delete', '/contacts');

		$this->assertResult($result)
			->hasStatusOf(405)
			->allowsMethods(['get', 'head', 'post', 'options']);
	}

	#[Test()]
	public function adds_head_to_allowed_methods_when_get_is_available(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('post', '/');

		$this->assertResult($result)
			->hasStatusOf(405)
			->allowsMethods(['get', 'head', 'options']);
	}

	#[Test()]
	public function does_not_add_head_when_get_is_not_available(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/ping');

		$this->assertResult($result)
			->hasStatusOf(405)
			->allowsMethods(['post', 'options']);
	}

	// ------------------------------------------------------------------
	// Bad request: required param missing
	// ------------------------------------------------------------------

	#[Test()]
	public function returns_bad_request_when_url_is_missing_required_segment(): void
	{
		// Contact\GetAction has a required `string $person` param. /contact
		// provides no segment for it, so the URL doesn't satisfy the handler's
		// signature -> 400.
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/contact');

		$this->assertResult($result)
			->hasStatusOf(400);
	}

	// ------------------------------------------------------------------
	// Not found
	// ------------------------------------------------------------------

	#[Test()]
	public function returns_not_found_when_no_handler_matches_url(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/no-such-resource');

		$this->assertResult($result)
			->hasStatusOf(404);
	}

	#[Test()]
	public function returns_unprocessable_content_when_segment_cannot_be_cast(): void
	{
		// /archives/2026/not-a-number: month param is `?int`; "not-a-number"
		// can't cast to int. The route is matched (Archives\GetAllAction
		// exists and the arg count fits), but the value is unprocessable.
		// -> 422 Unprocessable Content (URL well-formed, value invalid).
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/archives/2026/not-a-number');

		$this->assertResult($result)
			->hasStatusOf(422);
	}

	// ------------------------------------------------------------------
	// Variadic-in-parent: configuration error
	// ------------------------------------------------------------------

	#[Test()]
	public function throws_when_url_extends_past_a_variadic_bearing_parent(): void
	{
		// VariadicParamsInParent\GetAction has `int ...$params` — its variadic
		// would always absorb trailing URL segments, which means any child
		// route under VariadicParamsInParent\... is unreachable. This is a
		// configuration error in the handler tree; the router throws to
		// surface it rather than silently routing past the variadic.
		$sut = $this->createRouterWithDefaultConfig();

		$this->expectException(UnallowedVariadicParameter::class);

		$sut->route('get', '/variadic-params-in-parent/act');
	}

	#[Test()]
	public function discovery_skips_methods_with_misconfigured_handlers_but_finds_valid_ones(): void
	{
		// VariadicParamsInParent\GetAction has `int ...$params` — GET routing
		// for /variadic-params-in-parent/act throws UnallowedVariadicParameter.
		// VariadicParamsInParent\PostAction (no variadic) and
		// VariadicParamsInParent\Act\PostAction give POST a clean chain.
		//
		// A DELETE request should produce 405 with [post, options]. The GET
		// probe inside discoverAllowedMethods throws — the catch there
		// prevents it from poisoning POST discovery. GET is NOT advertised in
		// Allow because it's a real misconfig (the user can't actually do GET
		// here without hitting the exception).
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('delete', '/variadic-params-in-parent/act');

		$this->assertResult($result)
			->hasStatusOf(405)
			->allowsMethods(['post', 'options']);
	}

	#[Test()]
	public function does_not_throw_when_url_stays_within_a_variadic_bearing_handler(): void
	{
		// /variadic-params-in-parent/404/405 has no extension past the
		// variadic-bearing parent — 404 and 405 are simply trailing segments
		// absorbed by `int ...$params`. No misconfiguration; should match.
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', '/variadic-params-in-parent/404/405');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'VariadicParamsInParent\\GetAction')
			->hasArguments([404, 405]);
	}

	// ------------------------------------------------------------------
	// WebDAV / configurable supportedMethods
	// ------------------------------------------------------------------

	#[Test()]
	public function routes_to_handler_for_methods_registered_via_with_additional_methods(): void
	{
		$config = Config::create(self::DEFAULT_TEST_FIXTURES_NAMESPACE)
			->withAdditionalMethods('propfind');
		$sut = new Router($config);

		$result = $sut->route('propfind', '/contacts');

		$this->assertResult($result)
			->hasStatusOf(200)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Contacts\\PropfindAllAction');
	}

	// ------------------------------------------------------------------
	// RouteType is set on matched routes
	// ------------------------------------------------------------------

	#[Test()]
	#[DataProvider('routeTypeAssignments')]
	public function assigns_route_type_based_on_action_class_suffix(
		string $target,
		RouteType $expectedType
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route('get', $target);

		$this->assertSame($expectedType, $result->route?->type);
	}

	public static function routeTypeAssignments(): array
	{
		return [
			'GetAllAction -> Collection' => ['/contacts', RouteType::Collection],
			'GetOneAction -> Item' => ['/users/1', RouteType::Item],
			'GetAction -> Action' => ['/contact/daniel', RouteType::Action],
		];
	}

	#[Test()]
	public function throws_when_nested_restful_handler_exists_but_parent_was_not_addressed(): void
	{
		// /persons/schema/t walks Persons -> Persons\Schema (no person ID
		// addressed), then 't' is an arg of the Schema level. Persons\Schema\
		// GetOneAction exists with `string $person`. With the parent skipped,
		// the inherited-ID semantic implied by GetOneAction is broken — the
		// handler is unreachable as-defined.
		//
		// Rather than silently 404-ing, the router throws SignatureMismatch
		// to surface the misconfiguration. To reach Persons\Schema\GetOneAction
		// correctly, the URL would need to be /persons/{personid}/schema (or
		// /persons/{personid}/schema/{schemaid} with a 2-param handler).
		$sut = $this->createRouterWithDefaultConfig();

		$this->expectException(SignatureMismatch::class);

		$sut->route('get', '/persons/schema/t');
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function createRouterWithDefaultConfig(): Router
	{
		return new Router(self::DEFAULT_TEST_FIXTURES_NAMESPACE);
	}

	private function assertResult(Result $sut): ResultBuilder
	{
		return new ResultBuilder($this, $sut);
	}
}
