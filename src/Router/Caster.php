<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;

/**
 * Consumes one or more leading URL segments and produces a typed argument.
 * Register custom casters on the Config (see Config::withCaster) to support new
 * parameter types — enums, value objects, etc. — without changing the router.
 *
 * A caster reads from the front of $segments and reports, via CastResult, how
 * many it consumed (always >= 1). Scalars/enums/uuid consume 1; a value object
 * consumes one per constructor parameter (recursively, through the $chain).
 *
 * Two-method contract keeps "this caster doesn't handle this type" (supports())
 * separate from "this caster handles the type but the value is invalid"
 * (cast() throws CannotCast -> 422) and "ran out of segments mid-build"
 * (IncompleteValue -> 400). null is never a failure sentinel.
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
	 * @param list<string> $segments the remaining segments; read from the front, never mutated
	 * @psalm-mutation-free
	 * @throws IncompleteValue when a segment is needed but none is left (-> 400)
	 * @throws CannotCast when a segment is present but invalid for $type (-> 422)
	 */
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult;
}
