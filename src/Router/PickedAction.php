<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Route;
use Meraki\Http\RouteParameter;

/**
 * The result of picking an action class at one namespace level. Either a
 * matched route (with the parameter list it consumed, so the next level can
 * inherit it), or no match — with `$castFailed` flagging that a candidate's
 * signature fit by arg count but a value could not be cast (-> 422), and
 * `$incomplete` flagging that a candidate ran out of segments while building a
 * value with required (constructor) parameters still unfilled (-> 400).
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
		public bool $incomplete,
	) {
	}

	/**
	 * @param list<RouteParameter> $paramsConsumed
	 * @psalm-pure
	 */
	public static function matched(Route $route, array $paramsConsumed): self
	{
		return new self($route, $paramsConsumed, false, false);
	}

	/**
	 * @psalm-pure
	 */
	public static function noMatch(bool $castFailed, bool $incomplete = false): self
	{
		return new self(null, [], $castFailed, $incomplete);
	}
}
