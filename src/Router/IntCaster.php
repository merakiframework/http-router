<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;

/**
 * @psalm-immutable
 * @psalm-api
 */
final class IntCaster implements Caster
{
	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		return $type->name === 'int';
	}

	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(string $segment, Type $type): int
	{
		if (!preg_match('/^-?\d+$/', $segment)) {
			throw CannotCast::value($segment, $type);
		}

		return (int) $segment;
	}
}
