<?php

declare(strict_types=1);

namespace Meraki\Http;

/**
 * @psalm-immutable
 * @psalm-api
 */
final class RouteParameter
{
	/**
	 * @param Type[] $types
	 */
	public function __construct(public int $position, public array $types, public string $name)
	{
	}

	public function hasType(string $type): bool
	{
		foreach ($this->types as $t) {
			if ($t->name === $type) {
				return true;
			}
		}

		return false;
	}

	public function typesAsString(): string
	{
		return implode('|', $this->types);
	}

	public function samePositionAs(self $other): bool
	{
		return $this->position === $other->position;
	}

	public function sameTypesAs(self $other): bool
	{
		if (count($this->types) !== count($other->types)) {
			return false;
		}

		foreach ($this->types as $i => $t) {
			if (!$t->equals($other->types[$i])) {
				return false;
			}
		}

		return true;
	}

	public function sameNameAs(self $other): bool
	{
		return $this->name === $other->name;
	}

	public function equals(self $other): bool
	{
		return $this->position === $other->position
			&& $this->sameTypesAs($other);
	}

	public function __toString(): string
	{
		return sprintf('%s $%s', implode('|', $this->types), $this->name);
	}
}
