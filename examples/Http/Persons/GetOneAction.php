<?php
declare(strict_types=1);

namespace Project\Http\Persons;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
	public function __invoke(string $id)
	{
		return new TextResponse("GET /persons/$id");
	}
}
