<?php
declare(strict_types=1);

namespace Project\Types;

/**
 * A string-backed enum — cast from a URL segment via its backing value.
 */
enum Suit: string
{
	case Hearts = 'hearts';
	case Diamonds = 'diamonds';
	case Clubs = 'clubs';
	case Spades = 'spades';
}
