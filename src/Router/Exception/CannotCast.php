<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Router\Exception;
use Meraki\Http\Type;
use RuntimeException;

/**
 * Thrown by a Caster when it supports a parameter's type but the URL segment is
 * not a valid value for it (e.g. "not-a-number" for an int). The router catches
 * this while fitting a candidate and maps it to a 422 Unprocessable Content.
 */
final class CannotCast extends RuntimeException implements Exception
{
	/**
	 * @psalm-pure
	 */
	public static function value(string $segment, Type $type): self
	{
		return new self(sprintf('Cannot cast "%s" to type "%s".', $segment, $type->name));
	}

	/**
	 * @psalm-pure
	 */
	public static function noCasterFor(string $segment, string $types): self
	{
		return new self(sprintf('No caster could cast "%s" to any of: %s.', $segment, $types));
	}
}
