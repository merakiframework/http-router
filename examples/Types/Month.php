<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A pure (unbacked) enum used as a nested value-object constructor parameter,
 * e.g. Date(Year $year, Month $month, Day $day).
 */
enum Month
{
	case January;
	case February;
	case March;
	case April;
	case May;
	case June;
	case July;
	case August;
	case September;
	case October;
	case November;
	case December;
}
