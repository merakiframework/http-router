<?php
declare(strict_types=1);

namespace Project\Http\Contact;

use Laminas\Diactoros\Response\TextResponse;

final class PostAction
{
	public function __invoke(string $person)
	{
		return new TextResponse('POST /contact/'.$person);
	}
}
