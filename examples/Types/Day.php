<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A value object whose single constructor parameter is a union (int|string).
 */
final class Day
{
	public function __construct(public int|string $value)
	{
	}
}
