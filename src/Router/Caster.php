<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

/**
 * Consumes one or more leading URL segments and produces a typed argument.
 * Register custom casters on the Config (see Config::withCaster) to support new
 * parameter types — enums, value objects, etc. — without changing the router.
 *
 * A caster reads from the front of $segments and reports, via CastResult, how
 * many it consumed (always >= 1 on success). Scalars/enums/uuid consume 1; a
 * value object consumes one per constructor parameter (recursively, through the
 * $chain).
 *
 * Outcomes are returned, never thrown: CastResult::ok() with the value,
 * CastResult::cannotCast() when a segment is present but invalid for the type
 * (-> 422), or CastResult::incomplete() when segments run out mid-value (-> 400).
 *
 * Contract: $segments is guaranteed non-empty (the CasterChain handles the empty
 * case), so a caster may read $segments[0] directly.
 *
 * Casters must be immutable (held by an immutable Config) and their methods
 * mutation-free — they may read their own readonly state but must not mutate.
 *
 * @psalm-immutable
 * @psalm-api
 */
interface Caster
{
	/**
	 * @psalm-mutation-free
	 */
	public function supports(Type $type): bool;

	/**
	 * @param non-empty-list<string> $segments the remaining segments; read from the front, never mutated
	 * @psalm-mutation-free
	 */
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult;
}
