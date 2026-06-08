<?php
declare(strict_types=1);

namespace Project\Http\BusinessDetails;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke()
	{
		return new TextResponse('GET /business-details');
	}
}
