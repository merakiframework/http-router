<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A nested value object: each constructor parameter is itself a value object or
 * enum, so the caster recurses and the whole Date consumes one segment per leaf
 * (e.g. /2026/August/27 -> Date(Year(2026), Month::August, Day(27))).
 */
final class Date
{
	public function __construct(
		public Year $year,
		public Month $month,
		public Day $day,
	) {
	}
}
