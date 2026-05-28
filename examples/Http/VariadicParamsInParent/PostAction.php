<?php
declare(strict_types=1);

namespace Project\Http\VariadicParamsInParent;

use Laminas\Diactoros\Response\TextResponse;

/**
 * Non-variadic POST handler co-located with the variadic GetAction. Used to
 * demonstrate that the "variadic in parent prevents child routes" rule is
 * per-method: POST can still extend past VariadicParamsInParent into Act
 * because PostAction has no variadic, even though GetAction does.
 */
final class PostAction
{
	public function __invoke(): TextResponse
	{
		return new TextResponse('POST /variadic-params-in-parent');
	}
}
