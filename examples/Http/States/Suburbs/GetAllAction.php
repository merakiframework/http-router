<?php
declare(strict_types=1);

namespace Project\Http\States\Suburbs;

use Laminas\Diactoros\Response\TextResponse;

final class GetAllAction
{
	public function __invoke(string $stateAbbr)
	{
		return new TextResponse("GET /states/$stateAbbr/suburbs");
	}
}
