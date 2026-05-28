<?php
declare(strict_types=1);

namespace Project\Http\Persons\Schema;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
	public function __invoke(string $person): TextResponse
	{
		// should throw a signature mismatch exception as it doesnt
		return new TextResponse("GET /persons/schema/$person (GetOneAction)");
	}
}
