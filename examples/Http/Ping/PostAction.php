<?php
declare(strict_types=1);

namespace Project\Http\Ping;

use Laminas\Diactoros\Response\TextResponse;

final class PostAction
{
	public function __invoke()
	{
		return new TextResponse('Post /ping');
	}
}
