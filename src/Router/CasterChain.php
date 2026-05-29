<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

/**
 * The ordered set of casters, with the resolution logic: pick the first caster
 * whose supports() matches one of the (union) types and let it consume from the
 * front of the segments. Passed to each caster so value objects can recurse
 * (a constructor parameter is cast back through the same chain).
 *
 * Owns the single empty-segments guard: a caster must consume >= 1 segment, so
 * there is no point calling one with nothing left — that is an incomplete value.
 *
 * @psalm-immutable
 * @psalm-internal Meraki\Http
 */
final class CasterChain
{
	/**
	 * @param list<Caster> $casters
	 */
	public function __construct(private array $casters)
	{
	}

	/**
	 * Cast the leading segment(s) to the first of $types any caster accepts.
	 *
	 * @param list<string> $segments
	 * @psalm-mutation-free
	 */
	public function cast(array $segments, Type ...$types): CastResult
	{
		if ($segments === []) {
			return CastResult::incomplete();
		}

		$incomplete = false;

		foreach ($types as $type) {
			if ($type->isNull()) {
				continue;
			}
			foreach ($this->casters as $caster) {
				if (!$caster->supports($type)) {
					continue;
				}

				$result = $caster->cast($segments, $type, $this);

				if ($result->status === CastStatus::Successful) {
					return $result;
				}

				// This caster owns the type. Remember an incomplete value so a
				// later union member (e.g. int in Date|int) can still try, then
				// move on to the next union member.
				if ($result->status === CastStatus::IncompleteValue) {
					$incomplete = true;
				}

				break;
			}
		}

		return $incomplete ? CastResult::incomplete() : CastResult::cannotCast();
	}
}
