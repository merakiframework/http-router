<?php
declare(strict_types=1);

namespace Project\Http\Users;

use Laminas\Diactoros\Response\TextResponse;

final class DeleteOneAction
{
	public function __invoke(int|string $id)
	{
		return new TextResponse('DELETE /users/{' . $id . '}');
	}
}
