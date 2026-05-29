<?php
declare(strict_types=1);

namespace Meraki\Http;

/**
 * A single, non-union parameter type — the unit a Caster is asked about. Built
 * from one ReflectionNamedType (union/nullable types are flattened into a list
 * of these by RouteParameters::toTypes()).
 *
 * @psalm-immutable
 * @psalm-api
 */
final class Type
{
	public function __construct(
		public string $name,
		public bool $builtin,
		public bool $allowsNull,
	) {
	}

	/**
	 * @psalm-pure
	 */
	public static function fromReflection(\ReflectionNamedType $type): self
	{
		return new self($type->getName(), $type->isBuiltin(), $type->allowsNull());
	}

	/**
	 * Flatten a (possibly union/nullable) reflection type into a list of single
	 * named types — the unit a Caster is asked about.
	 *
	 * @return list<Type>
	 * @psalm-pure
	 */
	public static function listFromReflection(?\ReflectionType $type): array
	{
		if ($type instanceof \ReflectionUnionType) {
			$types = [];
			foreach ($type->getTypes() as $member) {
				if ($member instanceof \ReflectionNamedType) {
					$types[] = self::fromReflection($member);
				}
			}
			return $types;
		}

		if ($type instanceof \ReflectionNamedType) {
			return [self::fromReflection($type)];
		}

		return [];
	}

	public function isBuiltin(): bool
	{
		return $this->builtin;
	}

	public function allowsNull(): bool
	{
		return $this->allowsNull;
	}

	public function isNull(): bool
	{
		return $this->name === 'null';
	}

	public function equals(self $other): bool
	{
		return $this->name === $other->name && $this->builtin === $other->builtin;
	}

	public function __toString(): string
	{
		return $this->name;
	}
}
