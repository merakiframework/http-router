<?php
declare(strict_types=1);

namespace Project\Http\States\Suburbs;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
	public function __invoke(string $stateAbbr, string $suburbName)
	{
		// $stateAbbr = '';
		// $suburbName = '';
		return new TextResponse("GET /states/$stateAbbr/suburbs/$suburbName");
	}
}
