<?php
declare(strict_types=1);

namespace Project\Http\BusinessDetails\HolidayExceptions;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke()
	{
		return new TextResponse('GET /business-details/holiday-exceptions');
	}
}
