<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

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
	 * @param non-empty-list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		$segment = $segments[0];

		if (!preg_match('/^-?\d+$/', $segment)) {
			return CastResult::cannotCast();
		}

		return CastResult::ok((int) $segment, 1);
	}
}
