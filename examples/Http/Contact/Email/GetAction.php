<?php
declare(strict_types=1);

namespace Project\Http\Contact\Email;

use Laminas\Diactoros\Response\TextResponse;

/**
 * A POST request to this class will check if the contact can be reached.
 */
final class GetAction
{
	public function __invoke(string $person)
	{
		return new TextResponse("POST /contact/$person/email");
	}
}
