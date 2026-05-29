<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;

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
	 * @param list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		if ($segments === []) {
			throw IncompleteValue::ranOut($type);
		}

		$segment = $segments[0];

		if (!preg_match('/^-?\d+$/', $segment)) {
			throw CannotCast::value($segment, $type);
		}

		return new CastResult((int) $segment, 1);
	}
}
