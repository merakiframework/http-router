<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A value object with a single string constructor parameter.
 */
final class Slug
{
	public function __construct(public string $value)
	{
	}
}
