<?php
declare(strict_types=1);

namespace Project\Http\Cards;

use Laminas\Diactoros\Response\TextResponse;
use Project\Types\Suit;

/**
 * Enum parameter: GET /cards/{suit} binds the segment to the Suit enum
 * (string-backed) via the default EnumCaster.
 */
final class GetOneAction
{
	public function __invoke(Suit $suit)
	{
		return new TextResponse('GET /cards/' . $suit->value);
	}
}
