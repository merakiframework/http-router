<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

/**
 * The result of a Caster attempt: a status plus, when successful, the bound
 * value and how many segments it consumed (always >= 1, so the variadic binding
 * loop always makes progress). Non-successful results carry a null value and
 * zero consumed — read them only when isOk() is true.
 *
 * @psalm-immutable
 * @psalm-api
 */
final class CastResult
{
	private function __construct(
		public CastStatus $status,
		public mixed $value,
		public int $consumed,
	) {
	}

	/**
	 * @psalm-pure
	 */
	public static function ok(mixed $value, int $consumed): self
	{
		return new self(CastStatus::Successful, $value, $consumed);
	}

	/**
	 * @psalm-pure
	 */
	public static function cannotCast(): self
	{
		return new self(CastStatus::CannotCast, null, 0);
	}

	/**
	 * @psalm-pure
	 */
	public static function incomplete(): self
	{
		return new self(CastStatus::IncompleteValue, null, 0);
	}

	public function isOk(): bool
	{
		return $this->status === CastStatus::Successful;
	}
}
