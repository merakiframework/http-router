<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A value object with a single scalar constructor parameter (consumes 1 segment).
 */
final class Year
{
	public function __construct(public int $value)
	{
	}
}
