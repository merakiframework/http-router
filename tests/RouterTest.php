<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router;
use Meraki\Http\Router\Result;
use Meraki\Http\Router\Config;
use Meraki\Http\AssertionBuilder\Result as ResultBuilder;
use Meraki\Http\Router\Exception\SignatureMismatch;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use PHPUnit\Framework\TestCase;

/**
 * @covers Router::
 */
final class RouterTest extends TestCase
{
	/**
	 * Namespace where request-handlers used for testing are under.
	 */
	private const DEFAULT_TEST_FIXTURES_NAMESPACE = 'Project\\Http\\';

	/**
	 * @test
	 */
	public function default_config_is_created_with_correct_namespace(): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$actualNamespace = $sut->config->namespace;

		$this->assertEquals('Project\\Http', $actualNamespace);
	}

	/**
	 * @test
	 */
	public function adds_head_method_to_allowed_methods_if_get_is_available_but_head_is_not(): void
	{
		$expectedMethod = 'post';
		$expectedRequestTarget = '/';
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(405)
			->allowsMethods(['get', 'head']);
	}

	/**
	 * @test
	 */
	public function does_not_add_head_to_allowed_methods_if_get_is_not_allowed_either(): void
	{
		$expectedMethod = 'get';
		$expectedRequestTarget = '/ping';
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(405)
			->allowsMethods(['post']);
	}

	/**
	 * @test
	 */
	public function get_handler_is_returned_if_head_handler_doesnt_exist(): void
	{
		$expectedMethod = 'head';
		$expectedRequestTarget = '/contact';
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasNoClosestMatches()
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . 'Contact\\GetAction')
			->hasInvokeMethodOf($sut->config->invokeMethod)
			->hasNoArguments();
	}

	/**
	 * @test
	 * @dataProvider rootPathMappings
	 */
	public function root_path_routes_to_correct_handler(string $expectedMethod, string $expectedRequestTarget, string $expectedRequestHandler): void
	{
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedRequestHandler);
	}

	public function rootPathMappings(): array
	{
		// "Home" is the default subnamespace
		return [
			'GET /' => ['get', '/', 'Home\\GetAction'],
			'HEAD /' => ['head', '/', 'Home\\GetAction'], // not defined, so should map to GET handler
		];
	}

	/**
	 * @test
	 * @dataProvider notDefinedNamespaceSegments
	 */
	public function route_not_found_if_namespace_segment_not_exists(
		string $expectedMethod,
		string $expectedRequestTarget,
		string $expectedClass,
		int $expectedClosestMatches = 0
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(404)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->canBeMatchedWithHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasClosestMatchesOf($expectedClosestMatches);
	}

	public function notDefinedNamespaceSegments(): array
	{
		return [
			'/action' => ['get', '/test', 'Test\\GetAction'],
			'/resources' => ['get', '/tests', 'Tests\\GetAllAction'],

			// provides "Ping\GetAction" as closest match
			'/action/resource' => ['post', '/ping/user', 'Ping\\User\\PostAction', 1],
			'/action/resources' => ['post', '/ping/users', 'Ping\\Users\\PostAllAction', 1],

			// '/resources/<string> - arg as singular' => ['get', '/contacts/daniel', 'Contacts\\GetAllAction'],
			// '/resources/<string> - arg as plural' => ['get', '/contacts/daniels', 'Contacts\\GetAllAction']
		];
	}

	/**
	 * @test
	 * @dataProvider definedRequestTargetsWithoutParams
	 */
	public function request_target_is_found_without_params(
		string $expectedMethod,
		string $expectedRequestTarget,
		string $expectedClass
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasNoClosestMatches()
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasInvokeMethodOf($sut->config->invokeMethod)
			->hasNoArguments();
	}

	public function definedRequestTargetsWithoutParams()
	{
		return [
			'/resources, get method' => ['get', '/contacts', 'Contacts\\GetAllAction'],
			'/action, post method' => ['post', '/ping', 'Ping\\PostAction'],
		];
	}

	/**
	 * @test
	 * @dataProvider definedRequestTargetsWithParamsForExcludedPluralWord
	 */
	public function request_target_is_found_with_excluded_plural_word_and_params(
		string $expectedRequestTarget,
		string $expectedClass,
		array $expectedArgs
	): void {
		$expectedMethod = 'get';
		$config = Config::create(self::DEFAULT_TEST_FIXTURES_NAMESPACE)
			->excludePluralWords('archives', 'music');
		$sut = new Router($config);

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasNoClosestMatches()
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasInvokeMethodOf($sut->config->invokeMethod)
			->hasArguments($expectedArgs);
	}

	public function definedRequestTargetsWithParamsForExcludedPluralWord(): array
	{
		return [
			'/resources' => [
				'/archives',
				'Archives\\GetAllAction',
				[],
			],
			'/resources/<int?>' => [
				'/archives/2022',
				'Archives\\GetAllAction',
				[2022],
			],
			'/resources/<int?>/<int?>' => [
				'/archives/2022/12',
				'Archives\\GetAllAction',
				[2022, 12],
			],
			'/resources/<int?>/<int?>/<int?>' => [
				'/archives/2022/12/16',
				'Archives\\GetAllAction',
				[2022, 12, 16],
			],
			'/resources/<string?>' => [
				'/music/red-hot-chili-peppers',
				'Music\\GetAllAction',
				['red-hot-chili-peppers'],
			],
			'/resources/<string?>/<string?>/<string?>' => [
				'/music/red-hot-chili-peppers/californication/track-info/californication',
				'Music\\TrackInfo\\GetAction',
				['red-hot-chili-peppers', 'californication', 'californication'],
			],
		];
	}

	/**
	 * @test
	 * @dataProvider definedRequestTargetsWithParams
	 */
	public function request_target_is_found_with_params(
		string $expectedRequestTarget,
		string $expectedClass,
		array $expectedArgs
	): void {
		$expectedMethod = 'get';
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasNoClosestMatches()
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasInvokeMethodOf($sut->config->invokeMethod)
			->hasArguments($expectedArgs);
	}

	public function definedRequestTargetsWithParams(): array
	{
		return [
			'/action/<string>' => [
				'/contact/daniel',
				'Contact\\GetAction',
				['daniel'],
			],
			'/resource/<string?>/action' => [
				'/contact/daniel/email',
				'Contact\\Email\\GetAction',
				['daniel'],
			],
		];
	}

	/**
	 * @test
	 */
	public function lists_allowed_methods_if_provided_method_could_not_be_matched(
		string $expectedMethod = 'delete',
		string $expectedRequestTarget = '/contacts'
	): void {
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(405)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->allowsMethods(['head', 'get', 'post']);
	}

	public function routesWithDifferentMethods(): array
	{
		return [
			'POST ' => ['POST'],
		];
	}

	/**
	 * @test
	 */
	public function can_match_deeply_nested_trailing_segments_as_variadic_params(): void
	{
		$expectedMethod = 'get';
		$expectedRequestTarget = '/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control';
		$expectedClass = 'States\Suburbs\RegisteredBusinesses\GetAllAction';
		$config = Config::create(self::DEFAULT_TEST_FIXTURES_NAMESPACE)
			->excludePluralWords('registered-businesses');
		$sut = new Router($config);

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasNoClosestMatches()
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasInvokeMethodOf($sut->config->invokeMethod)
			->hasArguments(['qld', 'emerald', 'cleaning', 'pest-control']);
	}

	/**
	 * @test
	 */
	public function throws_error_if_parent_route_tries_accepting_trailing_url_segments(): void
	{
		$expectedMethod = 'get';
		$expectedRequestTarget = '/variadic-params-in-parent/act';
		$sut = $this->createRouterWithDefaultConfig();

		$this->expectException(UnallowedVariadicParameter::class);

		$result = $sut->route($expectedMethod, $expectedRequestTarget);
	}

	/**
	 * @test
	 */
	public function does_not_throw_error_if_trailing_url_segments_in_single_level_route(): void
	{
		$expectedMethod = 'get';
		$expectedRequestTarget = '/variadic-params-in-parent/404/405';
		$expectedClass = 'VariadicParamsInParent\\GetAction';
		$sut = $this->createRouterWithDefaultConfig();

		$result = $sut->route($expectedMethod, $expectedRequestTarget);

		$this->assertResult($result)
			->hasStatusOf(200)
			->usedMethodForMatch($expectedMethod)
			->usedRequestTargetForMatch($expectedRequestTarget)
			->hasNoClosestMatches()
			->hasRouteThat()
			->matchesRequestHandler(self::DEFAULT_TEST_FIXTURES_NAMESPACE . $expectedClass)
			->hasInvokeMethodOf($sut->config->invokeMethod)
			->hasArguments([404, 405]);
	}

	/**
	 * @test
	 */
	public function throws_error_if_param_from_parent_route_is_missing_from_child_route(): void
	{
		$parentRoute = new Route('Project\\Http\\MissingParameter\\GetAction', '__invoke');
		$childRoute = new Route('Project\\Http\\MissingParameter\\Act\\GetAction', '__invoke');
		$sut = $this->createRouterWithDefaultConfig();

		$this->expectExceptionObject(SignatureMismatch::missingRequiredParameter(
			$parentRoute,
			$childRoute,
			new RouteParameter(0, [], 'person', null)
		));

		$result = $sut->route('get', '/missing-parameter/daniel/act');
	}

	private function createRouterWithDefaultConfig(): Router
	{
		return new Router(self::DEFAULT_TEST_FIXTURES_NAMESPACE);
	}

	private function assertResult(Result $sut): ResultBuilder
	{
		return new ResultBuilder($this, $sut);
	}
}
