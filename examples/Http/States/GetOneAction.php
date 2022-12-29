<?php
declare(strict_types=1);

namespace Project\Http\States;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
	/**
	 * @param enum<qld,sa,tas,wa,nt,act,nsw,vic> $stateAbbr
	 */
	public function __invoke(string $stateAbbr)
	{
		return new TextResponse("GET /states/$stateAbbr");
	}
}
