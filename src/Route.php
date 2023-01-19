<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\RouteParameters;

/**
 * This class stores information needed to dispatch
 * or match a request-handler.
 *
 * @psalm-immutable
 */
final class Route
{
	public RouteParameters $parameters;

	/**
	 * @param class-string $requestHandler
	 * @param mixed[] $arguments
	 */
	public function __construct(
		public string $requestHandler,
		public string $invokeMethod,
		public array $arguments = [],
		RouteParameters $parameters = null,
	) {
		$this->parameters = $parameters ?: RouteParameters::reflectOn($requestHandler, $invokeMethod);
	}

	public function withArguments(mixed ...$arguments): self
	{
		return new self($this->requestHandler, $this->invokeMethod, $arguments, $this->parameters);
	}

	public function addArgument(mixed $arg): self
	{
		return $this->withArguments(...$this->arguments, ...[$arg]);
	}

	public function withParameters(RouteParameters $parameters): self
	{
		return new self($this->requestHandler, $this->invokeMethod, $this->arguments, $parameters);
	}

	public function toStringWithoutParameters(): string
	{
		return sprintf('%s::%s', $this->requestHandler, $this->invokeMethod);
	}

	public function toStringWithParameters(): string
	{
		return sprintf('%s::%s(%s)', $this->requestHandler, $this->invokeMethod, (string)$this->parameters);
	}

	public function __toString(): string
	{
		return $this->toStringWithParameters();
	}
}
