<?php
declare(strict_types=1);

namespace Meraki\Http\AssertionBuilder;

use Meraki\Http\Router\Result as RouterResult;
use PHPUnit\Framework\TestCase;

final class Result
{
	public function __construct(private TestCase $phpunit, private RouterResult $sut)
	{
	}

	public function allowsMethods(array $expected): self
	{
		$this->phpunit->assertNotNull($this->sut->allowedMethods);
		$this->phpunit->assertCount(count($expected), $this->sut->allowedMethods);

		foreach ($expected as $expectedMethod) {
			$this->phpunit->assertContains($expectedMethod, $this->sut->allowedMethods);
		}

		return $this;
	}

	public function allowsMethod(string $expected): self
	{
		$this->phpunit->assertNotNull($this->sut->allowedMethods);
		$this->phpunit->assertContains($expected, $this->sut->allowedMethods);

		return $this;
	}

	public function doesNotAllowMethod(string $expected): self
	{
		$this->phpunit->assertNotNull($this->sut->allowedMethods);
		$this->phpunit->assertNotContains($expected, $this->sut->allowedMethods);

		return $this;
	}

	public function canBeMatchedWithHandler(string $expected): self
	{
		$this->phpunit->assertNotNull($this->sut->handlerThatMatchesRequest);
		$this->phpunit->assertEquals($expected, $this->sut->handlerThatMatchesRequest);

		return $this;
	}

	public function hasRoute(): self
	{
		$this->phpunit->assertNotNull($this->sut->route);

		return $this;
	}

	public function hasRouteThat(): ResultHasRoute
	{
		$this->phpunit->assertNotNull($this->sut->route);

		return new ResultHasRoute($this->phpunit, $this->sut->route);
	}

	public function hasStatusOf(int $expected): self
	{
		$this->phpunit->assertEquals($expected, $this->sut->status);

		return $this;
	}

	public function usedMethodForMatch(string $expectedMethod): self
	{
		$this->phpunit->assertEquals($expectedMethod, $this->sut->method);

		return $this;
	}

	public function usedRequestTargetForMatch(string $expectedRequestTarget): self
	{
		$this->phpunit->assertEquals($expectedRequestTarget, $this->sut->requestTarget);

		return $this;
	}

	public function hasNoClosestMatches(): self
	{
		$this->phpunit->assertCount(0, $this->sut->closestMatches);

		return $this;
	}

	public function hasClosestMatchesOf(int $expected): self
	{
		$this->phpunit->assertCount($expected, $this->sut->closestMatches);

		return $this;
	}
}
