<?php
declare(strict_types=1);

namespace Project\Http\Users;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
	public function __invoke(int|string $id)
	{
		return new TextResponse('GET /users' . $id);
	}
}
