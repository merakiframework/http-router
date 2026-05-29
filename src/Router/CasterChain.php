<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;

/**
 * The ordered set of casters, with the resolution logic: pick the first caster
 * whose supports() matches one of the (union) types and let it consume from the
 * front of the segments. Passed to each caster so value objects can recurse
 * (a constructor parameter is cast back through the same chain).
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
	 * @throws IncompleteValue ran out of segments (-> 400)
	 * @throws CannotCast no caster matched, or the value was invalid (-> 422)
	 */
	public function cast(array $segments, Type ...$types): CastResult
	{
		$incomplete = null;

		foreach ($types as $type) {
			if ($type->isNull()) {
				continue;
			}
			foreach ($this->casters as $caster) {
				if (!$caster->supports($type)) {
					continue;
				}
				try {
					return $caster->cast($segments, $type, $this);
				} catch (IncompleteValue $e) {
					// Ran out of segments for this type; remember it but let a
					// later union member (e.g. int in Date|int) still try.
					$incomplete = $e;
					break;
				} catch (CannotCast) {
					// Segment present but invalid for this type; try next union member.
					break;
				}
			}
		}

		if ($incomplete !== null) {
			throw $incomplete;
		}

		throw CannotCast::noCasterFor($segments[0] ?? '', implode('|', $types));
	}
}
