<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Route;
use Meraki\Http\RouteParameter;

/**
 * The result of picking an action class at one namespace level. Either a
 * matched route (with the parameter list it consumed, so the next level can
 * inherit it), or no match — with `$castFailed` flagging that a candidate's
 * signature fit by arg count but a value could not be cast (-> 422).
 *
 * @internal
 * @psalm-internal Meraki\Http
 * @psalm-immutable
 */
final readonly class PickedAction
{
	/**
	 * @param list<RouteParameter> $paramsConsumed
	 */
	private function __construct(
		public ?Route $route,
		public array $paramsConsumed,
		public bool $castFailed,
	) {
	}

	/**
	 * @param list<RouteParameter> $paramsConsumed
	 * @psalm-pure
	 */
	public static function matched(Route $route, array $paramsConsumed): self
	{
		return new self($route, $paramsConsumed, false);
	}

	/**
	 * @psalm-pure
	 */
	public static function noMatch(bool $castFailed): self
	{
		return new self(null, [], $castFailed);
	}
}
