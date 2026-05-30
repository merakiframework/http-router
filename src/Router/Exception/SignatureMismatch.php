<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Router\Exception;
use Meraki\Http\Route;
use RuntimeException;

/**
 * @psalm-api
 */
final class SignatureMismatch extends RuntimeException implements Exception
{
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
}
