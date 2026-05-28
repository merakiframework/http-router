<?php
declare(strict_types=1);

namespace Project\Http\VariadicParamsInParent\Act;

use Laminas\Diactoros\Response\TextResponse;

/**
 * Child POST handler reachable via /variadic-params-in-parent/act. Coexists
 * fine alongside the broken GET chain (parent GetAction has variadic, child
 * GetAction throws when reached) because variadic checks are per-method.
 */
final class PostAction
{
	public function __invoke(): TextResponse
	{
		return new TextResponse('POST /variadic-params-in-parent/act');
	}
}
