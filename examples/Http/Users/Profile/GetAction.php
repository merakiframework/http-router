<?php
declare(strict_types=1);

namespace Project\Http\Users\Profile;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke(int|string $id)
	{
		return new TextResponse('GET /users/' . $id . '/profile');
	}
}
