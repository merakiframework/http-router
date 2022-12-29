<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Route;
use PHPUnit\Framework\TestCase;

/**
 * @covers Route::
 */
final class RouteTest extends TestCase
{
	private const REQUEST_HANDLER = 'Project\\Http\\Archives\\GetAllAction';
	private const INVOKE_METHOD = '__invoke';

	/**
	 * @test
	 */
	public function request_handler_is_set(): void
	{
		$sut = $this->createRoute();

		$this->assertEquals(self::REQUEST_HANDLER, $sut->requestHandler);
	}

	/**
	 * @test
	 */
	public function invoke_method_is_set(): void
	{
		$sut = $this->createRoute();

		$this->assertEquals(self::INVOKE_METHOD, $sut->invokeMethod);
	}

	public function creates_reflection_parameters_automatically(): void
	{
		$sut = $this->createRoute();
	}

	/**
	 * @test
	 */
	public function has_no_arguments_when_created(): void
	{
		$sut = $this->createRoute();

		$this->assertEmpty($sut->arguments);
	}

	private function createRoute(): Route
	{
		return new Route(self::REQUEST_HANDLER, self::INVOKE_METHOD);
	}
}
