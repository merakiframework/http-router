<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;

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
	 * @return list<int|float|string>
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(string $segment, Type $type): array
	{
		if (empty($segment)) {
			throw CannotCast::value($segment, $type);
		}

		$elements = explode(',', $segment);
		$elementType = self::inferElementType($elements[0]);
		$casted = [];

		foreach ($elements as $element) {
			if ($element === '') {
				throw CannotCast::value($segment, $type);
			}

			$value = self::castElement($element, $elementType);

			if ($value === null) {
				// An element doesn't match the list's inferred element type.
				throw CannotCast::value($segment, $type);
			}

			$casted[] = $value;
		}

		return $casted;
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
