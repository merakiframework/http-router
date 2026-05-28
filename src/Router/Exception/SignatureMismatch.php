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
	/**
	 * @psalm-mutation-free
	 */
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

	/**
	 * Thrown when a RESTful Item/Collection handler exists at a nested
	 * namespace but its parent collection wasn't addressed in the URL — i.e.
	 * the handler is unreachable as-defined, which is almost always a config
	 * error (forgot an ID segment, or named GetOneAction when GetAction was
	 * intended).
	 *
	 * @psalm-pure
	 */
	public static function nestedRestfulRouteRequiresAddressedParent(Route $childRoute): self
	{
		$template = 'Cannot route to "%s": it is a RESTful Item/Collection at a nested namespace, but the URL did not address the parent resource before this segment. To reach this handler, the URL must include an identifier for the parent collection, or the handler should be renamed to a plain action (e.g. GetAction) if no inherited context is required.';

		return new self(sprintf(
			$template,
			$childRoute->requestHandler
		));
	}

	/**
	 * @psalm-pure
	 */
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
