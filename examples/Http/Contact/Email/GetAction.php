<?php
declare(strict_types=1);

namespace Project\Http\Contact\Email;

use Laminas\Diactoros\Response\TextResponse;

/**
 * This handler demonstrates a verb/action route that is nested more than one level deep, and also
 * demonstrates that the parent resource's argument(s) are not inherited when the handler is an
 * Action (i.e. not GetAll/GetOne).
 */
final class GetAction
{
	public function __invoke(string $person)
	{
		return new TextResponse("POST /contact/email/$person");
	}
}
