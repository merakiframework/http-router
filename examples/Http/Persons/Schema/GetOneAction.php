<?php
declare(strict_types=1);

namespace Project\Http\Persons\Schema;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
	public function __invoke(string $id): TextResponse
	{
		return new TextResponse('should throw a signature mismatch exception as it doesnt "inherit" the parent param chain');
	}
}
