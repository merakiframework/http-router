<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;

/**
 * The universal caster: a `string` parameter accepts any URL segment verbatim
 * and never fails. This is why a `string`-typed parameter can never produce a
 * 422 — see the README design decisions.
 *
 * @psalm-immutable
 * @psalm-api
 */
final class StringCaster implements Caster
{
	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function supports(Type $type): bool
	{
		return $type->name === 'string';
	}

	/**
	 * @psalm-pure
	 */
	#[\Override]
	public function cast(string $segment, Type $type): string
	{
		return $segment;
	}
}
