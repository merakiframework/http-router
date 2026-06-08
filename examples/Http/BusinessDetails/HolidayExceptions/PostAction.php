<?php
declare(strict_types=1);

namespace Project\Http\BusinessDetails\HolidayExceptions;

use Laminas\Diactoros\Response\TextResponse;

final class PostAction
{
	public function __invoke()
	{
		return new TextResponse('POST /business-details/holiday-exceptions');
	}
}
