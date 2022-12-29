<?php
declare(strict_types=1);

namespace Project\Http\VariadicParamsInParent\Act;

use Laminas\Diactoros\Response\TextResponse;

// /variadic-params-in-parent/act
final class GetAction
{
	public function __invoke()
	{
		return new TextResponse("variadic params in parent error");
	}
}
