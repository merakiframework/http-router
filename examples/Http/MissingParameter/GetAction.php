<?php
declare(strict_types=1);

namespace Project\Http\MissingParameter;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke(string $person)
	{
		return new TextResponse('GET /missing-parameter/'.$person);
	}
}
