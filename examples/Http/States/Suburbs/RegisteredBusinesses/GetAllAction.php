<?php
declare(strict_types=1);

namespace Project\Http\States\Suburbs\RegisteredBusinesses;

use Laminas\Diactoros\Response\TextResponse;

final class GetAllAction
{
	public function __invoke(string $stateAbbr, string $suburbName, string ...$offersTheseServices)
	{
		return new TextResponse("GET /states/$stateAbbr/suburbs/$suburbName/registered-businesses");
	}
}
