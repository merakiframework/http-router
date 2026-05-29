<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

/**
 * The outcome of a Caster consuming one or more leading URL segments: the bound
 * value and how many segments it consumed (always >= 1, so the variadic binding
 * loop always makes progress).
 *
 * @psalm-immutable
 * @psalm-api
 */
final class CastResult
{
	public function __construct(
		public mixed $value,
		public int $consumed,
	) {
	}
}
