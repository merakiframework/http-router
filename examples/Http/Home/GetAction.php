<?php
declare(strict_types=1);

namespace Project\Http\Home;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke()
	{
		return new TextResponse('GET /');
	}
}
