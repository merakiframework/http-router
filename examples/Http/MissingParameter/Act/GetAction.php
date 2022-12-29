<?php
declare(strict_types=1);

namespace Project\Http\MissingParameter\Act;

use Laminas\Diactoros\Response\TextResponse;

// /error/missing-parameter/<person>/act
final class GetAction
{
	public function __invoke()
	{
		return new TextResponse("missing param error");
	}
}
