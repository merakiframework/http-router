<?php
declare(strict_types=1);

namespace Project\Http\Archives;

use Laminas\Diactoros\Response\TextResponse;

final class GetAllAction
{
	public function __invoke(?int $year = null, ?int $month = null, ?int $day = null)
	{
		if ($year && $month && $day) {
			return new TextResponse("GET /archives/$year/$month/$day");
		}

		if ($year && $month) {
			return new TextResponse("GET /archives/$year/$month");
		}

		if ($year) {
			return new TextResponse("GET /archives/$year");
		}

		return new TextResponse('GET /archives');
	}
}
