<?php
declare(strict_types=1);

namespace Project\Http\VariadicParamsInParent;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke(int ...$params)
	{
		return new TextResponse('GET /variadic-params-in-parent/'.implode('/', $params));
	}
}
