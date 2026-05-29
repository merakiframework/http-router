<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

/**
 * Builds a value object from URL segments via its constructor. Each constructor
 * parameter is resolved back through the chain, so a parameter may itself be a
 * scalar, an enum, a UUID, or another value object (arbitrary nesting, e.g.
 * `Date(Year $year, Month $month, Day $day)`). A value object therefore consumes
 * one segment per (recursive) leaf parameter; running out of segments while a
 * required parameter is unfilled yields CastResult::incomplete() (-> 400).
 *
 * Opt-in: register with Config::withCaster(new ValueObjectCaster()).
 *
 * @psalm-immutable
 * @psalm-api
 */
final class ValueObjectCaster implements Caster
{
	/**
	 * @psalm-mutation-free
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		/** @psalm-suppress ImpureFunctionCall */
		if ($type->isBuiltin() || enum_exists($type->name) || !class_exists($type->name)) {
			return false;
		}

		/** @psalm-suppress ImpureMethodCall */
		$reflection = new \ReflectionClass($type->name);

		/** @psalm-suppress ImpureMethodCall */
		if (!$reflection->isInstantiable()) {
			return false;
		}

		/** @psalm-suppress ImpureMethodCall */
		$constructor = $reflection->getConstructor();

		/** @psalm-suppress ImpureMethodCall */
		return $constructor !== null && $constructor->getNumberOfRequiredParameters() >= 1;
	}

	/**
	 * @param non-empty-list<string> $segments
	 * @psalm-mutation-free
	 */
	#[\Override]
	public function cast(array $segments, Type $type, CasterChain $chain): CastResult
	{
		/**
		 * @psalm-suppress ImpureMethodCall
		 * @var class-string $class
		 */
		$class = $type->name;
		$constructor = (new \ReflectionClass($class))->getConstructor();

		if ($constructor === null) {
			return CastResult::cannotCast();
		}

		$args = [];
		$consumed = 0;

		/** @psalm-suppress ImpureMethodCall */
		foreach ($constructor->getParameters() as $param) {
			$remaining = array_slice($segments, $consumed);

			if ($remaining === []) {
				/** @psalm-suppress ImpureMethodCall */
				if ($param->isOptional()) {
					break; // optional constructor params take their defaults
				}
				return CastResult::incomplete();
			}

			/** @psalm-suppress ImpureMethodCall */
			$sub = $chain->cast($remaining, ...Type::listFromReflection($param->getType()));

			if (!$sub->isOk()) {
				return $sub; // propagate cannotCast / incomplete
			}

			/** @psalm-suppress MixedAssignment */
			$args[] = $sub->value;
			$consumed += $sub->consumed;
		}

		/** @psalm-suppress ImpureMethodCall,MixedMethodCall */
		return CastResult::ok(new $class(...$args), $consumed);
	}
}
