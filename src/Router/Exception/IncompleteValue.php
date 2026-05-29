<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Type;

/**
 * Thrown by a Caster when it needs another URL segment to finish building a
 * value but none is left — e.g. a value object whose constructor still has
 * required parameters when the segments run out. The router treats this as a
 * missing required parameter (400), distinct from a present-but-invalid value
 * (plain CannotCast -> 422).
 */
final class IncompleteValue extends CannotCast
{
	/**
	 * @psalm-pure
	 */
	public static function ranOut(Type $type): self
	{
		return new self(sprintf('Ran out of URL segments while building a value of type "%s".', $type->name));
	}
}
