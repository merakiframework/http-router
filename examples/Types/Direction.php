<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A pure (unbacked) enum — cast from a URL segment by matching the case name.
 */
enum Direction
{
	case North;
	case South;
	case East;
	case West;
}
