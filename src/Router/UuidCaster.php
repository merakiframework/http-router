<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Casts a URL segment to a ramsey/uuid value. Treated as a fundamental type but
 * inert unless ramsey/uuid is installed (the application installs it itself) —
 * supports() returns false when the interface is absent.
 *
 * Targets the `UuidInterface` type. ramsey/uuid returns a lazy instance from
 * fromString() that satisfies UuidInterface but not the concrete version classes
 * (UuidV4, …), so version-specific parameter types are intentionally not handled
 * here (use UuidInterface, then check $uuid->getFields()->getVersion() in the
 * handler if needed). Consumes one segment.
 *
 * @psalm-immutable
 * @psalm-api
 */
final class UuidCaster implements Caster
{
	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		/** @psalm-suppress ImpureFunctionCall */
		return interface_exists(UuidInterface::class) && $type->name === UuidInterface::class;
	}

	/**
	 * @param non-empty-list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		try {
			/** @psalm-suppress ImpureMethodCall */
			$uuid = Uuid::fromString($segments[0]);
		} catch (\Throwable) {
			return CastResult::cannotCast();
		}

		return CastResult::ok($uuid, 1);
	}
}
