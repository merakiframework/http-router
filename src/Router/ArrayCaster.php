<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

/**
 * Casts a comma-separated URL segment (e.g. "1,2,3") into a homogeneous list.
 * PHP's `array` type carries no element type via reflection, so the element
 * type is inferred from the first element (int -> float -> string) and every
 * element must cast to it.
 *
 * @psalm-immutable
 * @psalm-api
 */
final class ArrayCaster implements Caster
{
	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		return $type->name === 'array';
	}

	/**
	 * @param non-empty-list<string> $segments
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		$segment = $segments[0];

		if (empty($segment)) {
			return CastResult::cannotCast();
		}

		$elements = explode(',', $segment);
		$elementType = self::inferElementType($elements[0]);
		$casted = [];

		foreach ($elements as $element) {
			if ($element === '') {
				return CastResult::cannotCast();
			}

			$value = self::castElement($element, $elementType);

			if ($value === null) {
				// An element doesn't match the list's inferred element type.
				return CastResult::cannotCast();
			}

			$casted[] = $value;
		}

		return CastResult::ok($casted, 1);
	}

	/**
	 * @psalm-pure
	 */
	private static function inferElementType(string $element): string
	{
		if (preg_match('/^-?\d+$/', $element)) {
			return 'int';
		}

		if ($element === (string) (float) $element) {
			return 'float';
		}

		return 'string';
	}

	/**
	 * @psalm-pure
	 */
	private static function castElement(string $element, string $type): int|float|string|null
	{
		return match ($type) {
			'int' => preg_match('/^-?\d+$/', $element) ? (int) $element : null,
			'float' => $element === (string) (float) $element ? (float) $element : null,
			'string' => $element,
			default => null,
		};
	}
}
