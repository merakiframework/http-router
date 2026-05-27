<?php
declare(strict_types=1);

namespace Project\Http\Persons;

use Laminas\Diactoros\Response\TextResponse;

final class GetAllAction
{
	public function __invoke()
	{
		return new TextResponse("GET /persons");
	}
}
