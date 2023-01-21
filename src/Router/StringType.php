<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use InvalidArgumentException;
use RuntimeException;

final class StringType
{
	/** @var array<string, string> */
	private const VALIDATION_REGEXES = [
		'int' => '/^\d+$/',
		'float' => '/^[+-]?\d+(\.\d+)?([eE][+-]?\d+)?$/',
		'string' => '/^.+$/',
	];

	public function __construct(private string $value)
	{
	}

	public static function fromString(string $value): self
	{
		return new self($value);
	}

	public static function fromFloat(float $value): self
	{
		return new self((string)$value);
	}

	public function castTo(string $type): mixed
	{
		$type = strtolower($type);

		return match ($type) {
			'string' => $this->castToString(),
			'int', 'integer' => $this->castToInt(),
			'float' => $this->castToFloat(),
			'array' => $this->castToArray(),
			default => throw new InvalidArgumentException("Cannot cast to '$type': type is not supported.")
		};
	}

	public function castToArray(): array
	{
		if (empty($this->value)) {
			throw new RuntimeException('Cannot cast to array: array is empty');
		}

		$list = explode(',', $this->value);
		$expectedType = '';

		// find element type for list
		foreach (['int', 'float', 'string'] as $type) {
			try {
				/** @psalm-suppress MixedAssignment */
				$el = self::fromString($list[0])->castTo($type);
				$expectedType = gettype($el) === 'double' ? 'float' : gettype($el);
				break;
			} catch (\Throwable $th) {
				// continue to next type
			}
		}

		if (!$expectedType) {
			throw new RuntimeException('cannot cast to array: could not determine element type');
		}

		foreach ($list as $index => $element) {
			if ($element === '') {
				throw new \RuntimeException('Cannot cast to array: missing element in list');
			}

			try {
				// cast array elements to correct type
				/** @psalm-suppress MixedAssignment */
				$el = self::fromString($element)->castTo($expectedType);
				/** @psalm-suppress MixedAssignment */
				$list[$index] = $el;
			} catch (RuntimeException $e) {
				// if casting throws exception then one of the elements is a different type
				throw new RuntimeException('cannot cast to array: elements are not all of the same type');
			}
		}

		return $list;
	}

	public function castToFloat(): float
	{
		$floatValue = (float) $this->value;

		if ($this->value === (string)$floatValue) {
			return $floatValue;
		}

		throw new RuntimeException('Cannot cast to float: casting will lose information.');
	}

	public function castToInt(): int
	{
		$intValue = (int) $this->value;

		if ($this->value === (string)$intValue) {
			return $intValue;
		}

		throw new RuntimeException('Cannot cast to integer: casting will lose information.');
	}

	public function castToString(): string
	{
		return $this->value;
	}

	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}

	public function equivalentTo(self $other): bool
	{
		return strcasecmp($this->value, $other->value) === 0;
	}
}
