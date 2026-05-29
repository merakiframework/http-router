<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;

/**
 * Casts a URL segment to a PHP enum case. Backed enums use ::tryFrom() (with the
 * segment coerced to the backing type); pure (unbacked) enums match the segment
 * against the case name, case-insensitively. Consumes one segment.
 *
 * @psalm-immutable
 * @psalm-api
 */
final class EnumCaster implements Caster
{
	/**
	 * @psalm-mutation-free
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		/** @psalm-suppress ImpureFunctionCall */
		return !$type->isBuiltin() && enum_exists($type->name);
	}

	/**
	 * @param list<string> $segments
	 * @psalm-mutation-free
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		if ($segments === []) {
			throw IncompleteValue::ranOut($type);
		}

		$segment = $segments[0];
		/** @var class-string<\UnitEnum> $enum */
		$enum = $type->name;

		/** @psalm-suppress ImpureMethodCall */
		$isBacked = (new \ReflectionEnum($enum))->isBacked();

		if ($isBacked) {
			/** @var class-string<\BackedEnum> $enum */
			$case = $this->fromBacked($enum, $segment, $type);
		} else {
			$case = $this->fromName($enum, $segment);
		}

		if ($case === null) {
			throw CannotCast::value($segment, $type);
		}

		return new CastResult($case, 1);
	}

	/**
	 * @param class-string<\BackedEnum> $enum
	 * @psalm-mutation-free
	 */
	private function fromBacked(string $enum, string $segment, Type $type): ?\BackedEnum
	{
		/** @psalm-suppress ImpureMethodCall */
		$backing = (string) (new \ReflectionEnum($enum))->getBackingType();

		if ($backing === 'int') {
			if (!preg_match('/^-?\d+$/', $segment)) {
				throw CannotCast::value($segment, $type);
			}

			return $enum::tryFrom((int) $segment);
		}

		return $enum::tryFrom($segment);
	}

	/**
	 * @param class-string<\UnitEnum> $enum
	 * @psalm-mutation-free
	 */
	private function fromName(string $enum, string $segment): ?\UnitEnum
	{
		// Case-insensitive: the request target is lower-cased for case-insensitive
		// route matching, but pure-enum case names are conventionally PascalCase.
		/** @psalm-suppress ImpureMethodCall */
		foreach ($enum::cases() as $case) {
			if (strcasecmp($case->name, $segment) === 0) {
				return $case;
			}
		}

		return null;
	}
}
