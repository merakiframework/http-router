<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Router\Exception;
use Meraki\Http\Route;
use Meraki\Http\RouteParameter;
use Meraki\Http\Router\Exception\InvalidArgument;
use RuntimeException;

final class SignatureMismatch extends RuntimeException implements Exception
{
	public static function incorrectTypes(Route $pRoute, Route $cRoute, RouteParameter $parentParam, RouteParameter $childParam): self
	{
		$template = 'Route "%s" must have the same type signature as parent resource "%s": expected type[s] "%s" at position %d, got "%s".';

		return new self(sprintf(
			$template,
			$cRoute->toStringWithoutParameters(),
			$pRoute->toStringWithoutParameters(),
			$parentParam->typesAsString(),
			$parentParam->position,
			$childParam->typesAsString()
		));
	}

	public static function missingRequiredParameter(Route $parentRoute, Route $childRoute, RouteParameter $parentParam): self
	{
		$template = 'Parameter #%d ($%s) from parent request-handler "%s" is missing from child request-handler "%s", or is not in the same position.';

		return new self(sprintf(
			$template,
			$parentParam->position,
			$parentParam->name,
			$parentRoute->requestHandler,
			$childRoute->requestHandler
		));
	}
}
