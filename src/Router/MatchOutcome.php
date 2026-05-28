<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Route;

/**
 * The result of a single `Router::tryMatch()` attempt: either a matched route
 * chain, or a failure reason. `$lastHandler` is the deepest handler class the
 * walk reached (used for diagnostic Result fields).
 *
 * @internal
 * @psalm-internal Meraki\Http
 * @psalm-immutable
 */
final readonly class MatchOutcome
{
	/**
	 * @param Route[] $matches
	 */
	private function __construct(
		public array $matches,
		public ?MatchFailure $failure,
		public string $lastHandler,
	) {
	}

	/**
	 * @param Route[] $matches
	 * @psalm-pure
	 */
	public static function matched(array $matches, string $lastHandler): self
	{
		return new self($matches, null, $lastHandler);
	}

	/**
	 * @psalm-pure
	 */
	public static function failed(MatchFailure $failure, string $lastHandler): self
	{
		return new self([], $failure, $lastHandler);
	}

	public function isMatch(): bool
	{
		return $this->failure === null && $this->matches !== [];
	}
}
