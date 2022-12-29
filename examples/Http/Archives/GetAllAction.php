<?php
declare(strict_types=1);

namespace Project\Http\Archives;

use Laminas\Diactoros\Response\TextResponse;

final class GetAllAction
{
	public function __invoke(int $year = null, int $month = null, int $day = null)
	{
		return new TextResponse('GET /archives');
	}
}
