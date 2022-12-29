<?php
declare(strict_types=1);

namespace Project\Http\Contacts;

use Laminas\Diactoros\Response\TextResponse;

final class PostAllAction
{
	public function __invoke()
	{
		// create new contact
		return new TextResponse('POST /contacts');
	}
}
