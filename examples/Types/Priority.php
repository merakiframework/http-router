<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * An int-backed enum — the URL segment must be an integer matching a case value.
 */
enum Priority: int
{
	case Low = 1;
	case Medium = 2;
	case High = 3;
}
