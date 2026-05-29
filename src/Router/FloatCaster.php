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
final class FloatCaster implements Caster
{
	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		return $type->name === 'float';
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
		$float = (float) $segment;

		// Reject anything that doesn't round-trip — i.e. casting would lose or
		// invent information (e.g. "abc" -> 0.0, "1,2" -> 1.0).
		if ($segment !== (string) $float) {
			throw CannotCast::value($segment, $type);
		}

		return new CastResult($float, 1);
	}
}
