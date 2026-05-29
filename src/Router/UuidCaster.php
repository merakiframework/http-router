<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;
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
	 * @param list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		if ($segments === []) {
			throw IncompleteValue::ranOut($type);
		}

		$segment = $segments[0];

		try {
			/** @psalm-suppress ImpureMethodCall */
			$uuid = Uuid::fromString($segment);
		} catch (\Throwable) {
			throw CannotCast::value($segment, $type);
		}

		return new CastResult($uuid, 1);
	}
}
