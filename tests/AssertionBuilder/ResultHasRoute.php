<?php
declare(strict_types=1);

namespace Meraki\Http\AssertionBuilder;

use Meraki\Http\Route;
use PHPUnit\Framework\TestCase;

final class ResultHasRoute
{
	public function __construct(private TestCase $phpunit, private Route $sut)
	{
	}

	public function matchesRequestHandler(string $expected): self
	{
		$this->phpunit->assertEquals($expected, $this->sut->requestHandler);

		return $this;
	}

	public function hasInvokeMethodOf(string $expected): self
	{
		$this->phpunit->assertEquals($expected, $this->sut->invokeMethod);

		return $this;
	}

	public function hasNoParameters(): self
	{
		$this->phpunit->assertCount(0, $this->sut->parameters);

		return $this;
	}

	public function hasNoArguments(): self
	{
		$this->phpunit->assertEmpty($this->sut->arguments);

		return $this;
	}

	public function hasArguments(array $expected): self
	{
		$this->phpunit->assertCount(count($expected), $this->sut->arguments);

		foreach ($expected as $expectedArgument) {
			$this->phpunit->assertContains($expectedArgument, $this->sut->arguments);
		}

		return $this;
	}

	// public function hasParameters(array $expected): self
	// {
	// 	$this->phpunit->assertCount(count($expected), $this->sut->parameters);

	// 	foreach ($expected as $expectedParameter) {
			// $this->phpunit->assertContains($expectedParameter, $this->sut->parameters);
	// 	}

	// 	return $this;
	// }
}
