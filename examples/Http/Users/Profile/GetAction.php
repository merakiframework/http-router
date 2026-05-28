<?php
declare(strict_types=1);

namespace Project\Http\Users\Profile;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke(int|string $id)
	{
		// intentionally showing that this handler is not a resource handler as it does not use the GetAll / GetOne convention.
		// This is to demonstrate that the router can handle non-resource routes without any special configuration.
		return new TextResponse('GET /users/profile/' . $id);
	}
}
