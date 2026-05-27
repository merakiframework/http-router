<?php
declare(strict_types=1);

namespace Project\Http\Contacts;

use Laminas\Diactoros\Response\TextResponse;

final class PropfindAllAction
{
	public function __invoke()
	{
		return new TextResponse('PROPFIND /contacts');
	}
}
