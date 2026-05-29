<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

/**
 * The universal caster: a `string` parameter accepts any URL segment verbatim
 * and never fails on a present segment. This is why a `string`-typed parameter
 * can never produce a 422 — see the README design decisions.
 *
 * @psalm-immutable
 * @psalm-api
 */
final class StringCaster implements Caster
{
	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		return $type->name === 'string';
	}

	/**
	 * @param non-empty-list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		return CastResult::ok($segments[0], 1);
	}
}
