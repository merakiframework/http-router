<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

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
	 * @param non-empty-list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		$segment = $segments[0];
		$float = (float) $segment;

		// Reject anything that doesn't round-trip — i.e. casting would lose or
		// invent information (e.g. "abc" -> 0.0, "1,2" -> 1.0).
		if ($segment !== (string) $float) {
			return CastResult::cannotCast();
		}

		return CastResult::ok($float, 1);
	}
}
