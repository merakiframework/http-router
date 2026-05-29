<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;

/**
 * Casts a raw URL segment into a typed argument. Register custom casters on the
 * Config (see Config::withCaster) to support new parameter types — enums, value
 * objects, etc. — without changing the router.
 *
 * Two-method contract keeps "this caster doesn't handle this type" (supports())
 * separate from "this caster handles the type but the value is invalid"
 * (cast() throws CannotCast -> 422). A null return is deliberately NOT used as a
 * failure signal, since null is a legitimate value for nullable parameters.
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
	 * @psalm-mutation-free
	 * @throws CannotCast when $segment is not a valid value for $type
	 */
	public function cast(string $segment, Type $type): mixed;
}
