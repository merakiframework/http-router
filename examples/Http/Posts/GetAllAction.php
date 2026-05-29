<?php
declare(strict_types=1);

namespace Project\Http\Posts;

use Laminas\Diactoros\Response\TextResponse;
use Project\Types\Date;

/**
 * Nested value-object parameter (opt-in ValueObjectCaster): GET /posts/{y}/{m}/{d}
 * binds three segments into one Date(Year, Month, Day) argument. With no segments,
 * the optional ?Date defaults to null; with too few, the required constructor
 * parameters are unfilled -> 400.
 */
final class GetAllAction
{
	public function __invoke(?Date $date = null)
	{
		if ($date === null) {
			return new TextResponse('GET /posts');
		}

		return new TextResponse(sprintf(
			'GET /posts/%d/%s/%s',
			$date->year->value,
			$date->month->name,
			(string) $date->day->value
		));
	}
}
