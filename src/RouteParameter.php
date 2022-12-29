<?php

declare(strict_types=1);

namespace Meraki\Http;

/**
 * @psalm-immutable
 */
final class RouteParameter
{
	/**
	 * @param string[] $types
	 */
	public function __construct(public int $position, public array $types, public string $name)
	{
	}

	public function hasType(string $type): bool
	{
		return in_array($type, $this->types);
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
		return $this->types === $other->types;
	}

	public function sameNameAs(self $other): bool
	{
		return $this->name === $other->name;
	}

	public function equals(self $other): bool
	{
		return $this->position === $other->position
			&& $this->types === $other->types;
	}

	public function __toString(): string
	{
		return sprintf('%s $%s', implode('|', $this->types), $this->name);
	}
}
