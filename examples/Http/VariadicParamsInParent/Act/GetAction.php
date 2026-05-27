<?php
declare(strict_types=1);

namespace Project\Http\VariadicParamsInParent\Act;

use Laminas\Diactoros\Response\TextResponse;

// /variadic-params-in-parent/act
final class GetAction
{
	public function __invoke()
	{
		return new TextResponse("should throw an UnallowedVariadicParameter exception because the parent action has a variadic parameter");
	}
}
