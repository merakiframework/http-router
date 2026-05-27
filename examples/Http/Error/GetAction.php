<?php
declare(strict_types=1);

namespace Project\Http\Error;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke(string ...$other)
	{
		return new TextResponse('GET /error/'.implode('/', $other));
	}
}
