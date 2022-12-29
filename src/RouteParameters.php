<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\RouteParameter;
use RuntimeException;

/**
 * @psalm-immutable
 */
final class RouteParameters implements \Countable
{
	/**
	 * @todo sort out parameter positions to make sure unique and in order
	 * @param RouteParameter[] $required
	 * @param RouteParameter[] $optional
	 * @param RouteParameter|null $variadic
	 */
	public function __construct(
		public array $required = [],
		public array $optional = [],
		public ?RouteParameter $variadic = null
	) {
	}

	/**
	 * @param class-string $fqcn
	 */
	public static function reflectOn(string $fqcn, string $method): self
	{
		/** @var RouteParameter[] $required */
		/** @var RouteParameter[] $optional */
		/** @var RouteParameter[]|null $variadic */
		$required = [];
		$optional = [];
		$variadic = null;
		$reflectionMethod = new \ReflectionMethod($fqcn, $method);

		foreach ($reflectionMethod->getParameters() as $param) {
			if ($param->isVariadic()) {
				$variadic = new RouteParameter(
					$param->getPosition(),
					self::toTypeList($param->getType()),
					$param->getName()
				);
			} elseif ($param->isOptional()) {
				$optional[] = new RouteParameter(
					$param->getPosition(),
					self::toTypeList($param->getType()),
					$param->getName()
				);
			} else {
				$required[] = new RouteParameter(
					$param->getPosition(),
					self::toTypeList($param->getType()),
					$param->getName()
				);
			}
		}

		return new self($required, $optional, $variadic);
	}

	public function addRequired(RouteParameter $param): self
	{
		/** @var RouteParameter[] $required */
		$required = array_merge($this->required, [$param]);

		return new self($required, $this->optional, $this->variadic);
	}

	public function addOptional(RouteParameter $param): self
	{
		/** @var RouteParameter[] $optional */
		$optional = array_merge($this->optional, [$param]);

		return new self($this->required, $optional, $this->variadic);
	}

	public function withVariadic(RouteParameter $param): self
	{
		return new self($this->required, $this->optional, $param);
	}

	public function hasVariadic(): bool
	{
		return $this->variadic !== null;
	}

	public function count(): int
	{
		return count($this->required) + count($this->optional) + ($this->variadic ? 1 : 0);
	}

	public function getAtPosition(int $position): ?RouteParameter
	{
		foreach ($this->all() as $param) {
			if ($param->position === $position) {
				return $param;
			}
		}

		return null;
	}

	public function replaceInPosition(RouteParameter $parameter): self
	{
		return $this->replaceAtPosition($parameter->position, $parameter);
	}

	public function __toString(): string
	{
		return implode(', ', $this->all());
	}

	public function replaceAtPosition(int $position, RouteParameter $otherParam): self
	{
		$required = $this->required;

		foreach ($required as $index => $thisParam) {
			if ($thisParam->position === $position) {
				$required[$index] = $otherParam;
				return new self($required, $this->optional, $this->variadic);
			}
		}

		$optional = $this->optional;

		foreach ($optional as $index => $thisParam) {
			if ($thisParam->position === $position) {
				$optional[$index] = $otherParam;
				return new self($this->required, $optional, $this->variadic);
			}
		}

		if ($this->variadic && $this->variadic->position === $position) {
			return new self($this->required, $this->optional, $otherParam);
		}

		throw new RuntimeException(sprintf('Could not find a parameter at position %d.', $position));
	}

	/**
	 * @return RouteParameter[]
	 */
	public function all(): array
	{
		return array_merge(
			$this->required,
			$this->optional,
			$this->variadic ? [$this->variadic] : []
		);
	}

	/**
	 * @return RouteParameter[]
	 */
	public function allExceptVariadic(): array
	{
		return array_merge($this->required, $this->optional);
	}

	/**
	 * @return string[]
	 */
	private static function toTypeList(?\ReflectionType $type): array
	{
		/** @var string[] $types */
		$types = [];

		if ($type instanceof \ReflectionUnionType) {
			foreach ($type->getTypes() as $t) {
				$types = array_merge($types, self::toTypeList($t));
			}
		} elseif ($type instanceof \ReflectionNamedType) {
			$types[] = $type->getName();
		}

		return $types;
	}
}
