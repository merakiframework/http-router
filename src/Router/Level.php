<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

/**
 * One step of the URL walk: a namespace reached so far, plus the URL segments
 * consumed as arguments at that level (i.e. segments that did not extend the
 * namespace further).
 *
 * @internal
 * @psalm-internal Meraki\Http
 * @psalm-immutable
 */
final readonly class Level
{
	/**
	 * @param list<string> $args
	 */
	public function __construct(
		public string $namespace,
		public array $args,
	) {
	}
}
