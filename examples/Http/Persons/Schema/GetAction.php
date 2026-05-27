<?php
declare(strict_types=1);

namespace Project\Http\Persons\Schema;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke()
	{
		return new TextResponse("GET /persons/schema (GetAction)");
	}
}
