<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
	private const REQUEST_HANDLER = 'Project\\Http\\Archives\\GetAllAction';
	private const INVOKE_METHOD = '__invoke';

	#[Test()]
	public function request_handler_is_set(): void
	{
		$sut = $this->createRoute();

		$this->assertEquals(self::REQUEST_HANDLER, $sut->requestHandler);
	}

	#[Test()]
	public function invoke_method_is_set(): void
	{
		$sut = $this->createRoute();

		$this->assertEquals(self::INVOKE_METHOD, $sut->invokeMethod);
	}

	public function creates_reflection_parameters_automatically(): void
	{
		$sut = $this->createRoute();
	}

	#[Test()]
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
